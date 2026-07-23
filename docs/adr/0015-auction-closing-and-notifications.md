# ADR-0015: Encerramento de leilão, vencedor e notificações

## Status

Aceito

## Contexto

Até a Fase 10, um leilão `ACTIVE` cujo `ends_at` chega nunca vira `CLOSED` — nada detecta o fim, ninguém é avisado de nada. Faltavam três coisas: decidir quem venceu (e se alguém venceu — `reserve_price` pode nunca ser atingido), fechar o leilão de forma segura mesmo com anti-sniping (Fase 10) potencialmente adiando `ends_at` bem no último instante, e avisar as pessoas certas (quem foi superado, quem venceu).

## Decisão

**Fechamento**: `AuctionClosingCommand` (`php artisan auctions:close-ended`) é um processo próprio — como `AuctionTimerBroadcastCommand`, não um consumer RabbitMQ — que a cada 5 segundos (configurável) busca ids de leilões `ACTIVE` com `ends_at` já passado (`AuctionRepository::activeIdsEndingBefore()`, uma query sem lock, deliberadamente leve) e chama `CloseAuctionUseCase::execute($id)` para cada um. O use case repete o padrão de `PlaceBidUseCase` (ADR-0006): `findByIdForUpdate()` dentro de uma transação, e **re-checa** status e `ends_at` depois de adquirir o lock — o id veio de uma leitura sem lock momentos antes, e um lance na janela de anti-sniping pode ter empurrado `ends_at` para frente exatamente nesse intervalo. Sem essa re-checagem, dois leilões poderiam fechar incorretamente: um que já não está mais `ACTIVE`, ou um cujo prazo real já não passou mais.

**Vencedor**: `Auction::close(?winnerId, ?winningAmount)` funde dois casos diferentes no mesmo resultado — "ninguém deu lance" e "o lance mais alto nunca atingiu o `reserve_price`" — ambos terminam com `winner_id: null` no evento `AuctionClosed`. Do ponto de vista de quem recebe o evento (broadcast, notificação), as duas situações pedem exatamente a mesma reação: nenhuma venda aconteceu. O agregado não sabe *quem* é o autor do lance vencedor (só guarda `highest_bid_id`) — `CloseAuctionUseCase` resolve isso com uma consulta a `BidRepository::findById()` antes de chamar `close()`, mantendo o agregado livre de uma dependência de repositório.

**Notificações**: o módulo `Notification`, vazio até aqui, ganhou corpo mínimo — não uma máquina de estados rica, um registro simples com "lido/não lido" como única regra. `Shared\Domain\Contracts\NotificationDispatcher` é o contrato que os módulos Auction usam para pedir uma notificação sem depender de `Modules\Notification` diretamente (o mesmo padrão de `BidderLookup`/`SellerLookup`); `Shared\Domain\Contracts\UserEmailLookup` é o inverso — o `Notification` module pedindo um e-mail ao `User` module sem depender dele. Dois tipos nesta fase: `outbid` (`SendBidNotificationConsumer`, preenchido agora — a fila e o consumer já existiam vazios desde a Fase 6) e `auction_won` (`SendWonNotificationConsumer`, novo).

`SendBidNotificationConsumer` descobre quem foi superado sem nenhum estado novo: como `Auction::placeBid()` exige `amount >= current_value + minimum_increment`, os lances de um leilão são estritamente crescentes — o lance mais recente *diferente* do que acabou de chegar (via `BidRepository::recentForAuction()`) é, por construção, exatamente o lance que acabou de ser superado.

**E-mail via Horizon**: `NotificationDispatcherAdapter::dispatch()` grava o registro, transmite `notification.created` (`private-App.Models.User.{id}`, o canal já registrado desde a Fase 2) direto — sem RabbitMQ de novo, já está rodando dentro de um consumer — e enfileira `NotificationMail` via `Mail::to($email)->queue(...)`. Sem credenciais SMTP reais: `MAIL_MAILER=log` (já era o default) escreve o e-mail formatado no log da aplicação, prova suficiente de que o conteúdo e o roteamento pela fila (Horizon, `QUEUE_CONNECTION=redis`) estão corretos sem depender de infraestrutura externa.

## Consequências

**Positivas**

- Provado ponta a ponta num ambiente real: um lance de verdade, a extensão por anti-sniping empurrando o prazo, o fechamento identificando o vencedor certo, o registro de notificação com os dados certos, e o e-mail (`"You won \"...\" for 110.00."`) aparecendo no log — tudo na mesma cadeia, sem simular nenhum passo.
- `Auction::close()` testado nos quatro resultados possíveis (vencedor sem reserva, sem lance nenhum, reserva não atingida, reserva atingida no valor exato) isoladamente no agregado, e depois de novo via o pipeline completo (`CloseAuctionUseCase` → integration event → consumer → broadcast, com o conteúdo exato do broadcast verificado).

**Negativas / trade-offs aceitos**

- `AuctionClosingCommand` varre todo leilão ativo vencido a cada tick, mesma bacia de escala aceita para `AuctionTimerBroadcastCommand` (ADR-0014) — adequado ao tamanho desta demo, não para uma escala muito maior.
- `Notification::Domain\Aggregates\Notification` é propositalmente pobre — sem eventos de domínio próprios, sem métodos além de `markAsRead()`. Suficiente para o que existe hoje (outbid, auction_won); qualquer regra nova mais rica (preferências de canal, agrupamento, etc.) provavelmente pede um redesenho, não uma extensão deste.
