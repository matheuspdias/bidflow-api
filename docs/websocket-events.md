# Eventos WebSocket

Documento vivo, atualizado a cada fase que adiciona ou muda um evento de broadcast. Ver [ADR-0011](adr/0011-reverb-websocket.md) para a estratégia geral de WebSocket e [ADR-0012](adr/0012-presence-channel-without-webhooks.md) para o canal de presence.

Todos os eventos deste documento trafegam pelo canal de presence `presence-auction.{auctionId}` (upgrade de `private-auction.{auctionId}` na Fase 8 — mesma regra de autorização, agora com classificação de papel), exceto quando indicado.

Um cliente entrando numa tela de leilão deve chamar `GET /api/auctions/{id}/live` (ver [ADR-0013](adr/0013-recent-bids-redis-feed.md)) **antes** de se inscrever no canal — ele traz o estado atual e o feed recente; os eventos abaixo só cobrem o que muda a partir daquele instante. Depois de uma queda de conexão, a mesma chamada com `?after_bid_id={último id visto}` devolve exatamente os lances perdidos, não uma janela fixa recente (Fase 13, [ADR-0017](adr/0017-reconnection-gap-fill.md)).

## `bid.placed`

Entrada de feed: um lance foi aceito. Cliente deve **acrescentar** ao feed de lances, não recalcular estado a partir dele.

Disparado por: `BroadcastBidConsumer` (Fase 7), reagindo ao integration event `auction.bid_placed`.

```json
{
    "id": 12,
    "auction_id": 33,
    "bidder_id": 82,
    "amount": "130.00",
    "placed_at": "2026-07-23T01:59:05+00:00"
}
```

## `auction.updated`

Resync de estado resumido do leilão — não uma entrada de feed. Cliente deve usar para atualizar o cabeçalho/estado atual, não para popular o histórico de lances.

Disparado por: `BroadcastBidConsumer` (Fase 7), junto com `bid.placed`, na mesma ação (mas como evento separado — ver ADR-0011 para o porquê da separação).

```json
{
    "auction_id": 33,
    "status": "active",
    "current_value": "130.00",
    "participant_count": 1
}
```

## `viewers.updated`

Contagem de espectadores ao vivo do leilão — não é o mesmo número que `auction.updated.participant_count` (que conta apenas quem já deu lance; um espectador que nunca dá lance conta aqui e nunca lá).

Disparado por: `BroadcastViewerCountConsumer` (Fase 8), reagindo a `auction.user_joined`/`auction.user_left`.

```json
{
    "auction_id": 33,
    "viewer_count": 2
}
```

## `timer.updated`

Tick de contagem regressiva sincronizada — corrige o drift do relógio do cliente perto do fim do leilão, não é a fonte primária da contagem (o cliente já tem `ends_at` desde a Fase 3/9 e pode contar sozinho enquanto falta muito tempo).

Disparado por: `AuctionTimerBroadcastCommand` (Fase 10) — um processo próprio, não um consumer RabbitMQ, rodando a cada segundo. Só transmitido para leilões `ACTIVE` com `ends_at` dentro de `config('auctions.timer.broadcast_window_seconds')` (default 300s) — ver ADR-0014.

```json
{
    "auction_id": 33,
    "seconds_remaining": 47,
    "ends_at": "2026-07-23T02:10:00+00:00"
}
```

## `auction.extended`

O anti-sniping empurrou `ends_at` para frente. Cliente deve resincronizar imediatamente o prazo exibido, sem esperar o próximo `timer.updated`.

Disparado por: `BroadcastAuctionExtendedConsumer` (Fase 10), reagindo ao integration event `auction.auction_extended` (publicado pelo próprio agregado `Auction`, dentro da mesma transação que aceitou o lance — ver ADR-0014).

```json
{
    "auction_id": 33,
    "new_ends_at": "2026-07-23T02:12:00+00:00",
    "extensions_count": 1
}
```

## `auction.ended`

O leilão fechou — com ou sem vencedor. `winner_id` é `null` quando ninguém deu lance ou quando o `reserve_price` nunca foi atingido (`Auction::close()` funde os dois casos no mesmo resultado: sem venda).

Disparado por: `BroadcastAuctionEndedConsumer` (Fase 11), reagindo ao integration event `auction.auction_closed`, publicado por `CloseAuctionUseCase` — chamado por `AuctionClosingCommand`, um processo próprio (como o timer) que fecha leilões `ACTIVE` cujo `ends_at` já passou. Ver ADR-0015.

```json
{
    "auction_id": 33,
    "winner_id": 82,
    "final_price": "130.00"
}
```

## `dashboard.updated` (canal `private-dashboard`)

O único evento deste documento fora de `presence-auction.{auctionId}` — um retrato completo das métricas de negócio, não um delta. Canal privado, não presence: ver ADR-0018 para por que nenhuma classificação de papel existe aqui ainda.

Disparado por: `BroadcastBusinessMetricsCommand` (Fase 14), um processo próprio (como o timer/closer) recalculando e transmitindo a cada 5 segundos.

```json
{
    "auctions": {"scheduled": 5, "active": 12, "closed": 340, "cancelled": 8},
    "total_bids": 1850,
    "total_revenue": "45230.00",
    "live_viewers_total": 37,
    "generated_at": "2026-07-23T04:06:37+00:00"
}
```

## Autenticação do canal

`POST /broadcasting/auth` (Bearer token Sanctum — **não** cookie/sessão, ver ADR-0011) com `channel_name` (`presence-auction.{id}`) e `socket_id`. Qualquer usuário autenticado pode se inscrever — a resposta inclui `channel_data` com a classificação de papel daquele usuário *para aquele leilão específico* (ADR-0012):

```json
{"user_id": "82", "user_info": {"id": 82, "role": "bidder"}}
```

`role` é um de `seller` (dono do leilão), `bidder` (já deu pelo menos um lance nele) ou `viewer` (nenhum dos dois). É por leilão, não um papel global do usuário — o vendedor do leilão A é apenas `viewer` no canal do leilão B.

## Pendente (fases futuras)

- `auction.ended` — leilão encerrado, com ou sem vencedor (Fase 11).
- `notification.created` — nova notificação para o usuário, canal `private-user.{id}` (Fase 11).
