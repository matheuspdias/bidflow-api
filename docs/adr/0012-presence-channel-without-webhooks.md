# ADR-0012: Canal de presence sem webhooks do Reverb

## Status

Aceito

## Contexto

O plano original desta fase assumia que "webhooks do Reverb em connect/subscribe/unsubscribe acionam um controller pequeno" — o mesmo modelo que Pusher/Ably oferecem (um callback HTTP para a aplicação quando alguém entra ou sai de um canal). Investigando o código-fonte do `laravel/reverb` instalado (`vendor/laravel/reverb/src/`), essa suposição se mostrou incorreta: não existe nenhuma chave `webhooks` em `config/reverb.php` e nenhuma referência a "webhook" em todo o pacote. O join/leave de um canal de presence é implementado inteiramente dentro do processo do Reverb — `InteractsWithPresenceChannels::subscribe()`/`unsubscribe()` (`vendor/laravel/reverb/src/Protocols/Pusher/Channels/Concerns/InteractsWithPresenceChannels.php`) apenas transmite quadros internos do protocolo Pusher (`pusher_internal:member_added`/`member_removed`) diretamente às conexões WebSocket já inscritas no canal — nunca chama de volta a camada HTTP da aplicação.

## Decisão

Em vez de um endpoint HTTP dedicado (a alternativa mais óbvia, mas que exigiria o **cliente** se autoreportar via chamadas de join/leave — frágil: um cliente que fecha a aba sem esse aviso nunca "sai"), a solução observa os próprios eventos internos do Laravel que o Reverb já dispara **dentro do seu processo** (`reverb:start` roda a mesma aplicação Laravel, com os mesmos service providers — os listeners registrados em `AuctionServiceProvider::boot()` valem tanto para o container `app` quanto para o `reverb`):

- **`MessageSent`** (`Connection::send()` dispara este evento para *toda* mensagem que o servidor envia, quadros internos de presence inclusive) — `TrackPresenceChannelMembership` inspeciona o payload; se for `pusher_internal:member_added`/`member_removed` num canal `presence-auction.{id}`, atualiza um set do Redis (`SADD`/`SREM auction:{id}:viewers`) e — só quando o próprio Redis confirma que era de fato uma mudança de estado (`SADD`/`SREM` retornando 1, não 0) — dispara `UserJoinedAuction`/`UserLeftAuction`.
- **Lacuna 1 — o último espectador a sair**: `broadcast()` do Reverb envia o quadro `member_removed` para "todas as outras conexões do canal"; quando resta zero, o `foreach` não executa nenhum `send()`, e `MessageSent` nunca dispara para essa saída. Mas exatamente nesse instante (`Channel::unsubscribe()`, ao ver `connections->isEmpty()`) o Reverb dispara **`ChannelRemoved`** — `ReleasePresenceOnChannelEmpty` usa esse sinal para esvaziar o que sobrou no set do Redis (0 ou 1 usuário) e disparar `UserLeftAuction` para ele.
- **Lacuna 2 — o primeiro espectador a entrar**: simétrica à lacuna 1 e sistemática (acontece em toda sessão, não é um caso raro) — o primeiro assinante nunca tem ninguém para receber seu próprio `member_added`, então também nunca passa por `MessageSent`. `ChannelCreated` dispara exatamente nessa transição 0→1, mas no instante do disparo o canal ainda está vazio (`EventHandler::subscribe()` chama `findOrCreate()` — que dispara `ChannelCreated` — e só na linha seguinte chama `$channel->subscribe(...)`, de forma síncrona). `RecordFirstPresenceMember` contorna isso agendando a leitura via `React\EventLoop\Loop::futureTick()`: como o Reverb roda sobre o loop de eventos do ReactPHP, o callback só executa depois que a call stack síncrona atual (que inclui o `subscribe()` seguinte) termina — nesse ponto `$channel->connections()` já contém a conexão recém-adicionada.

O `viewers.updated` (contagem ao vivo) continua passando pelo mesmo pipeline RabbitMQ das demais integration events (`auction.user_joined`/`auction.user_left` → `BroadcastViewerCountConsumer`), em vez de transmitir direto do processo do Reverb — mesmo sendo tecnicamente possível pular esse salto (o gatilho já nasce dentro do processo do Reverb). A escolha aqui foi consistência arquitetural: manter todo evento de domínio passando por domain event → integration event → RabbitMQ → consumer → broadcast, o mesmo pipeline usado por lances e leilões, em vez de um atalho pontual só porque era tecnicamente possível — dado que este projeto existe para demonstrar essa arquitetura.

`RabbitMqConsumerCommand` ganhou `additionalRoutingKeys()` para permitir que `BroadcastViewerCountConsumer` vincule uma única fila a `auction.user_joined` **e** `auction.user_left` — sem isso seriam necessários dois consumers idênticos por completo, só divergindo na routing key. O retry-republish também passou a usar a routing key com que a mensagem realmente chegou (`$message->getRoutingKey()`), não sempre a primária — necessário agora que uma fila pode multiplexar mais de um tipo de evento.

O canal em si (`routes/channels.php`) subiu de `private-auction.{id}` para `presence-auction.{id}` — mesma regra de autorização (qualquer usuário autenticado), mas agora classificando um papel (`seller`/`bidder`/`viewer`) relativo àquele leilão específico, devolvido como `user_info` na resposta de `/broadcasting/auth`. `BidPlacedBroadcastEvent` e `AuctionUpdatedBroadcastEvent` migraram de `PrivateChannel` para `PresenceChannel` no mesmo nome de canal — presence é um superconjunto de private, nenhum outro ajuste foi necessário.

## Consequências

**Positivas**

- Provado ponta a ponta com dois clientes WebSocket reais concorrentes (script mínimo falando o protocolo Pusher, não Echo): o primeiro a entrar já é contado corretamente (`viewer_count: 1`), o segundo entrando/saindo é visto pelo primeiro em tempo real com a contagem certa, e a saída do último (esvaziando o canal) zera o set do Redis corretamente.
- Nenhuma dependência nova de infraestrutura — reaproveita RabbitMQ, Redis e o pipeline de integration events já existentes.

**Negativas / trade-offs aceitos**

- Acoplado a detalhes internos do `laravel/reverb` que não fazem parte da sua API pública documentada (`MessageSent`/`ChannelCreated`/`ChannelRemoved`, o protocolo interno `pusher_internal:*`, e a suposição de que o Reverb roda sobre o event loop do ReactPHP) — uma atualização futura do pacote pode mudar esse comportamento sem quebrar nenhum contrato formal. Isolado inteiramente em `Infrastructure\Listeners` (nunca vaza para `Domain`/`Application`), então o raio de impacto de uma mudança futura fica contido.
- `TrackPresenceChannelMembership` roda para *toda* mensagem que o Reverb envia a qualquer cliente, em qualquer canal (não só presence) — o filtro por tipo de evento é barato, mas é overhead real, aceitável na escala desta demo.
