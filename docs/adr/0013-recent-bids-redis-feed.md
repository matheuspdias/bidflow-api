# ADR-0013: Feed de lances recentes via Redis, com fallback para o Postgres

## Status

Aceito

## Contexto

Uma tela de leilão ao vivo precisa de duas coisas antes de começar a ouvir o WebSocket: o estado atual do leilão e um histórico recente de lances para preencher o feed (o WS só empurra o *próximo* lance a partir do momento em que o cliente se conecta — nunca o que já aconteceu antes). `BidRepository::recentForAuction()` (Fase 4) já resolve isso consultando a tabela transacional `bids`, mas essa é exatamente a tabela que a Fase 4 protege com lock pessimista (`SELECT ... FOR UPDATE`) no caminho de escrita — toda tela de leilão popular fazendo essa mesma query de leitura a cada carregamento/reconexão soma carga num caminho que já é sensível.

## Decisão

`UpdateAuctionStatsConsumer` (o consumer que já reage a `auction.bid_placed` para manter contadores no Redis, desde a Fase 6) ganhou mais um efeito colateral: `LPUSH` do lance recém-chegado numa lista `auction:{id}:recent_bids`, seguido de `LTRIM` mantendo só as 50 entradas mais novas. `GET /api/auctions/{id}/live` lê dessa lista primeiro (`LRANGE`, O(1) por prática) e só cai para `BidRepository::recentForAuction()` — a mesma query de sempre contra `bids` — quando a lista do Redis está vazia.

Esse fallback não é uma defesa contra um cenário hipotético: é o comportamento correto e esperado em pelo menos dois casos reais e prováveis — um leilão com lances registrados antes desta feature existir, ou qualquer momento depois de um restart/flush do Redis (que aqui não tem persistência configurada, de propósito — é cache, não fonte de verdade). Sem o fallback, a tela ficaria com o feed vazio nesses casos mesmo havendo lances de verdade no leilão.

`viewer_count` no mesmo payload vem do set de presence do Redis introduzido na Fase 8 (`SCARD auction:{id}:viewers`, ver ADR-0012) — deliberadamente um número diferente de `auction.participant_count` (persistido, conta só quem já deu lance). Reaproveitar a mesma fonte que já alimenta `viewers.updated` no lugar de manter uma segunda contagem evita as duas caírem fora de sincronia.

## Consequências

**Positivas**

- Leitura O(1) no caminho quente (toda vez que alguém abre a tela de um leilão), sem tocar a tabela `bids` no caso comum.
- Nenhuma infraestrutura nova — reaproveita o Redis e o consumer já existentes desde a Fase 6.
- Fallback testado explicitamente (não só o caminho feliz): `tests/Feature/Auction/LiveAuctionSnapshotTest.php` cobre tanto a lista do Redis populada quanto vazia.

**Negativas / trade-offs aceitos**

- Mais um lugar (além dos contadores já existentes) onde `UpdateAuctionStatsConsumer` grava no Redis a partir do mesmo evento — aceito em vez de um consumer dedicado só para isso porque ambos são a mesma categoria de efeito colateral (projeção em Redis de `auction.bid_placed`), e um consumer novo só divergindo na routing key seria duplicação sem ganho.
- A lista do Redis é best-effort — uma falha entre o `LPUSH` e o `LTRIM`, ou uma mensagem nunca entregue a este consumer (fila caída, por exemplo), deixa o feed levemente desatualizado até o fallback assumir; aceitável porque o dado nunca deixa de existir, só de estar quente no cache.
