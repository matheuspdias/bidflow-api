# ADR-0016: Histórico, ganhos/perdas e rankings via contratos cross-module

## Status

Aceito

## Contexto

Os quatro endpoints stub desde a Fase 2 (`/api/profile/bids`, `/api/profile/auctions/won`, `/api/profile/auctions/lost`, `/api/rankings`) vivem no módulo `User` (é "minha atividade", uma pergunta sobre o usuário autenticado), mas todo o dado que respondem — lances, leilões, quem venceu — pertence ao módulo `Auction`. Preencher os stubs significava, necessariamente, o módulo `User` perguntar coisas ao módulo `Auction` sem depender dos seus internals (ADR-0001/0003).

Ao tentar implementar "leilões que venci", uma lacuna real apareceu: `Auction::close()` (Fase 11) sempre decidiu o vencedor, mas nunca persistiu isso em lugar nenhum consultável — só existia, de forma transitória, no payload do evento `AuctionClosed`, consumido uma vez pelos consumers de broadcast/notificação e depois perdido. Sem uma coluna, "leilões que venci" não tinha como ser respondido sem recomputar a regra de negócio (bids + reserve_price) fora do agregado, duplicando-a.

## Decisão

**Coluna `auctions.winner_id`** (nullable, `FK users`), retroativa à Fase 11: `Auction::close()` agora também atribui `$this->winnerId`, persistido por `EloquentAuctionRepository` como qualquer outro campo do agregado. Nenhuma migração de dado histórico foi necessária (nenhum leilão real havia fechado antes desta fase).

**Três contratos novos em `Shared\Domain\Contracts`**, um por pergunta, implementados por adapters no módulo `Auction` e injetados no `ActivityController` (módulo `User`, renomeado de `ActivityStubController` agora que deixou de ser stub):

- `BidHistoryLookup::paginateForBidder()` — `bids` join `auctions` (para o nome do leilão), mais recente primeiro.
- `WonLostAuctionsLookup::paginateWon()`/`paginateLost()` — "ganhei" é `status=closed AND winner_id=eu`; "perdi" é `status=closed AND existe um bid meu nesse leilão AND (winner_id é nulo OU não sou eu)` — a mesma fusão de "ninguém venceu" e "perdi para outro alguém" do lado do bidder que `Auction::close()` já faz do lado do evento.
- `BuyerRankingLookup::topWinners()` — ver metodologia no README.

Os três devolvem **arrays simples** (`{data, meta}` ou `list<array>`), não um DTO do domínio do `Auction` — o contrato é a forma, não um tipo vazando através da fronteira de módulo. `BuyerRankingLookup` devolve só `{user_id, wins}`; o enriquecimento com o nome do comprador acontece dentro do `ActivityController`, no módulo `User`, que já tem `UserRepository` — mantém `Auction` livre de qualquer motivo para conhecer nomes de usuário.

**Efeito colateral encontrado durante os testes, não hipotético**: dois lances no mesmo leilão, escritos em sucessão rápida (comum em teste, possível em produção sob lances simultâneos), podem cair no mesmo `created_at` — colunas `timestamp()` do Laravel usam precisão de 0 casas decimais por padrão no Postgres. Ordenar só por `created_at` (usado por `BidRepository::recentForAuction()` desde a Fase 4 — a mesma consulta de onde `SendBidNotificationConsumer`, Fase 11, deriva "quem foi superado") desempata de forma não determinística nesse caso. Corrigido adicionando `id` (sempre crescente com a ordem de inserção) como critério de desempate em `recentForAuction()` e em `BidHistoryLookupAdapter`.

## Consequências

**Positivas**

- `winner_id` sendo uma coluna de verdade significa que a metodologia de rankings inteira é uma única consulta agregada (`GROUP BY winner_id`) — nenhuma lógica de negócio duplicada fora do agregado.
- Provado com os quatro endpoints reais via HTTP contra dados de verdade (leilão fechado com vencedor, leilão sem vencedor por reserva não atingida, lances concorrentes na mesma tela).

**Negativas / trade-offs aceitos**

- `ActivityController` acumula quatro responsabilidades de leitura relacionadas mas distintas num só controller — aceito porque são, literalmente, "minha atividade", a mesma seção de perfil; um controller por endpoint aqui seria fragmentação sem ganho.
- A correção do desempate por `id` só cobre os dois lugares que hoje precisam de ordem estrita entre lances; qualquer consulta nova que assuma "mais recente" via `created_at` sozinho precisa lembrar da mesma pegadinha.
