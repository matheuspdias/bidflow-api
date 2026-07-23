# Walkthrough de arquitetura

Este documento existe para uma leitura em ordem — um jeito de percorrer o sistema inteiro seguindo um único lance do início ao fim, em vez de pular entre 19 ADRs sem saber por onde começar. Cada seção aponta para o ADR que decidiu aquele pedaço, para quem quiser o "por quê" completo; aqui o objetivo é só o "como se encaixa".

Se você está lendo este projeto pela primeira vez, comece por [README.md](../README.md) para o objetivo/stack/como rodar, depois volte aqui.

## O sistema em uma frase

Um monólito modular (`Auction`, `User`, `Notification`, `Dashboard`, `Auth` — [ADR-0001](adr/0001-monolito-modular.md)) onde módulos nunca conhecem os internals uns dos outros, só contratos publicados em `Shared\Domain\Contracts` ([ADR-0003](adr/0003-shared-kernel-contracts.md)); cada mudança de estado relevante vira um evento de domínio, que uma camada de tradução converte em evento de integração publicado no RabbitMQ ([ADR-0008](adr/0008-domain-vs-integration-events.md)), consumido por processos independentes que atualizam projeções, disparam notificações e transmitem via WebSocket (Laravel Reverb — [ADR-0011](adr/0011-reverb-websocket.md)).

## Um lance, do HTTP ao navegador de outra pessoa

1. **`POST /auctions/{id}/bids`** chega com um header `Idempotency-Key` obrigatório — `EnsureIdempotentBidRequest` verifica se essa chave já foi vista para este bidder; se sim, devolve a resposta cacheada sem tocar em nada mais ([ADR-0007](adr/0007-bid-idempotency-strategy.md)).

2. **`PlaceBidUseCase`** abre uma transação e faz `SELECT ... FOR UPDATE` no leilão (`AuctionRepository::findByIdForUpdate()`) — o lock pessimista que serializa lances concorrentes no mesmo leilão ([ADR-0006](adr/0006-pessimistic-locking-bid-concurrency.md), provado com processos de SO forkados de verdade, não threads simuladas). Dentro da mesma transação: `Auction::placeBid()` valida (status `ACTIVE`, vendedor não pode dar lance no próprio leilão, valor mínimo — [ADR-0005](adr/0005-auction-lifecycle.md)), o lance é persistido, `Auction::extendIfWithinAntiSnipingWindow()` roda (empurra `ends_at` se o lance caiu na janela final — [ADR-0014](adr/0014-anti-sniping-and-synchronized-timer.md)), e um log de auditoria é gravado — **mesmo para lances rejeitados**, dentro da mesma transação.

3. **Só depois do commit**, os domain events acumulados (`BidPlaced`, possivelmente `AuctionExtended`) são despachados. Um listener em `Infrastructure\Listeners` traduz cada um para um integration event e publica no RabbitMQ via `SafeIntegrationEventPublisher` — uma falha do broker aqui nunca derruba a resposta HTTP, só fica registrada em `failed_integration_events` para replay ([ADR-0008](adr/0008-domain-vs-integration-events.md)).

4. **Do outro lado do RabbitMQ**, vários consumers independentes reagem ao mesmo `auction.bid_placed`, cada um com sua própria fila, cada um idempotente por uma claim atômica contra `processed_events` — não um check-then-act, que tem uma corrida real quando duas entregas do mesmo evento chegam quase juntas ([ADR-0010](adr/0010-at-least-once-idempotent-consumers.md)):
   - `UpdateAuctionStatsConsumer` incrementa contadores no Redis e alimenta a lista de lances recentes ([ADR-0013](adr/0013-recent-bids-redis-feed.md)).
   - `PersistBidHistoryConsumer` popula o read model `bid_history` (CQRS — a tabela `bids` transacional nunca é a fonte de leitura de histórico).
   - `SendBidNotificationConsumer` descobre quem foi superado (o lance anterior, por construção — lances só crescem) e notifica via `NotificationDispatcher` ([ADR-0016](adr/0016-activity-and-rankings-cross-module-lookups.md), o contrato; [ADR-0015](adr/0015-auction-closing-and-notifications.md), a notificação em si).
   - `BroadcastBidConsumer` transmite `bid.placed` e `auction.updated` no canal de presence do leilão.

5. **No navegador de outra pessoa**, olhando o mesmo leilão: uma conexão WebSocket já inscrita em `presence-auction.{id}` recebe `bid.placed` (entrada de feed) e `auction.updated` (resync de estado) em poucos milissegundos — sem dar refresh. Se essa pessoa também está olhando quem mais está na tela, o mesmo canal já carregava classificação de papel (`seller`/`bidder`/`viewer`) desde a autenticação do canal ([ADR-0012](adr/0012-presence-channel-without-webhooks.md)).

## E se ninguém estiver olhando quando o leilão termina?

Um processo próprio (`AuctionClosingCommand`, não um consumer — um relógio, como o timer) varre leilões `ACTIVE` vencidos a cada 5 segundos, tranca cada candidato individualmente (o mesmo padrão lock-então-confira do lance) e fecha via `Auction::close()` — que decide o vencedor (ou decide que não houve venda, se ninguém deu lance ou o `reserve_price` nunca foi atingido — os dois casos viram o mesmo resultado) ([ADR-0015](adr/0015-auction-closing-and-notifications.md)). Isso publica `auction.auction_closed`, que dois consumers pegam: um transmite `auction.ended`, o outro notifica o vencedor (registro + e-mail via Horizon).

## As duas lacunas que o plano original não previu

Duas vezes neste projeto, uma suposição do plano original não bateu com o que o código de terceiros realmente faz, e a solução ficou documentada como a decisão mais interessante da fase:

- **Reverb não tem webhooks** ([ADR-0012](adr/0012-presence-channel-without-webhooks.md)) — join/leave de presence só existe como quadros internos do protocolo Pusher trocados dentro do próprio processo do Reverb. A saída foi observar os eventos internos do Laravel que o próprio `reverb:start` dispara (`MessageSent`, `ChannelCreated`, `ChannelRemoved`), com duas lacunas de borda simétricas (primeiro a entrar, último a sair) cobertas por mecanismos diferentes.
- **`created_at` sozinho não desempata lances no mesmo segundo** ([ADR-0016](adr/0016-activity-and-rankings-cross-module-lookups.md)) — Postgres `timestamp()` por padrão não guarda frações de segundo; `id` como critério de desempate adicional resolveu isso em todo lugar que precisava de ordem estrita entre lances.

## Os dois dashboards

Depois do leilão em si, duas perguntas diferentes sobre o sistema como um todo:

- **Negócio** ([ADR-0018](adr/0018-business-dashboard.md)): quantos leilões em cada estado, receita total, espectadores ao vivo — tudo dado que já existe no módulo `Auction`, respondido através de um contrato (`BusinessMetricsLookup`) que o módulo `Dashboard` usa sem depender dos internals do `Auction`.
- **Técnico** ([ADR-0019](adr/0019-technical-dashboard.md)): profundidade de fila (API de management do RabbitMQ), conexões WebSocket (API HTTP do próprio Reverb), throughput/latência por consumer (`RabbitMqConsumerCommand::recordMetrics()`, um método vazio desde a Fase 6 esperando exatamente por esta fase). Diferente do dashboard de negócio, nenhum contrato cross-module aqui — é infraestrutura compartilhada, não dado de um módulo de domínio.

Os dois seguem o mesmo par de caminhos que o resto do sistema: uma chamada REST para a carga inicial, um processo próprio transmitindo o mesmo retrato a cada 5 segundos via WebSocket.

## Onde ir a partir daqui

- [README.md](../README.md) — stack, como rodar, contratos de cada endpoint, estrutura de testes.
- [docs/websocket-events.md](websocket-events.md) — payload exato de cada evento WebSocket.
- [docs/openapi.yaml](openapi.yaml) — a API REST inteira, machine-readable.
- `docs/adr/` — o porquê de cada decisão, na ordem em que foram tomadas.
