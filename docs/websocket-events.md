# Eventos WebSocket

Documento vivo, atualizado a cada fase que adiciona ou muda um evento de broadcast. Ver [ADR-0011](adr/0011-reverb-websocket.md) para a estratégia geral de WebSocket e [ADR-0012](adr/0012-presence-channel-without-webhooks.md) para o canal de presence.

Todos os eventos deste documento trafegam pelo canal de presence `presence-auction.{auctionId}` (upgrade de `private-auction.{auctionId}` na Fase 8 — mesma regra de autorização, agora com classificação de papel), exceto quando indicado.

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

## Autenticação do canal

`POST /broadcasting/auth` (Bearer token Sanctum — **não** cookie/sessão, ver ADR-0011) com `channel_name` (`presence-auction.{id}`) e `socket_id`. Qualquer usuário autenticado pode se inscrever — a resposta inclui `channel_data` com a classificação de papel daquele usuário *para aquele leilão específico* (ADR-0012):

```json
{"user_id": "82", "user_info": {"id": 82, "role": "bidder"}}
```

`role` é um de `seller` (dono do leilão), `bidder` (já deu pelo menos um lance nele) ou `viewer` (nenhum dos dois). É por leilão, não um papel global do usuário — o vendedor do leilão A é apenas `viewer` no canal do leilão B.

## Pendente (fases futuras)

- `timer.updated` — tempo restante do leilão, a cada segundo (Fase 10).
- `auction.extended` — anti-sniping estendeu o prazo (Fase 10).
- `auction.ended` — leilão encerrado, com ou sem vencedor (Fase 11).
- `notification.created` — nova notificação para o usuário, canal `private-user.{id}` (Fase 11).
