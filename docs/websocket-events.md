# Eventos WebSocket

Documento vivo, atualizado a cada fase que adiciona ou muda um evento de broadcast. Ver [ADR-0011](adr/0011-reverb-websocket.md) para a estratégia geral de WebSocket.

Todos os eventos deste documento trafegam pelo canal privado `private-auction.{auctionId}`, exceto quando indicado.

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

## Autenticação do canal

`POST /broadcasting/auth` (Bearer token Sanctum — **não** cookie/sessão, ver ADR-0011) com `channel_name` e `socket_id`. Qualquer usuário autenticado pode se inscrever em `private-auction.{id}` — não há restrição adicional nesta fase (a classificação de papel vem na Fase 8, canal de presence).

## Pendente (fases futuras)

- `viewers.updated` — contagem de espectadores ao vivo (Fase 8, canal de presence).
- `timer.updated` — tempo restante do leilão, a cada segundo (Fase 10).
- `auction.extended` — anti-sniping estendeu o prazo (Fase 10).
- `auction.ended` — leilão encerrado, com ou sem vencedor (Fase 11).
- `notification.created` — nova notificação para o usuário, canal `private-user.{id}` (Fase 11).
