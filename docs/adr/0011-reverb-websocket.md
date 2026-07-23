# ADR-0011: Laravel Reverb para WebSocket

## Status

Aceito

## Contexto

Bidding em tempo real precisa de um canal de push server→cliente: quando alguém dá um lance, todo mundo olhando aquele leilão precisa ver o novo valor sem dar refresh. Laravel tem três caminhos oficialmente suportados para isso: Pusher (SaaS pago, protocolo proprietário), Ably (SaaS pago, protocolo próprio), e Reverb (servidor WebSocket próprio da Laravel, self-hosted, fala o protocolo Pusher).

## Decisão

**Reverb**, rodando como container próprio no `docker-compose` (ocioso desde a Fase 0, com corpo real a partir desta fase). Falar o protocolo Pusher significa que, no dia em que o frontend precisar, `laravel-echo` + `pusher-js` funcionam sem modificação — a compatibilidade de protocolo é gratuita, não um trabalho extra.

**Self-hosted em vez de SaaS** é a decisão central: um projeto de portfólio existe para *demonstrar* arquitetura, e depender de uma conta paga de terceiro (Pusher/Ably) tanto adiciona uma dependência externa desnecessária quanto esconde exatamente a parte que vale mostrar — como o WebSocket se encaixa no resto do sistema orientado a eventos. Reverb sendo Laravel puro também significa que a mesma stack de deploy (Docker Compose, sem serviço gerenciado adicional) cobre tudo.

**História de escala via Redis** (não implementada agora, documentada para quando for necessária): Reverb suporta múltiplas instâncias atrás de um load balancer usando Redis como *scaling driver* (pub/sub entre instâncias, para que uma mensagem publicada na instância A chegue a um cliente conectado na instância B). Para esta demo, uma única instância Reverb é suficiente — o Redis que já roda no `docker-compose` (cache, Horizon) seria reaproveitado como scaling driver do Reverb sem precisar de infraestrutura nova, bastaria configurar `REVERB_SCALING_ENABLED=true` e apontar para a mesma conexão Redis.

**A pegadinha de auth: `/broadcasting/auth` não é `api/*`.** O atalho `channels:` do `withRouting()` sempre registra `/broadcasting/auth` sob o grupo de middleware `web` (sessão + CSRF) — que nunca autentica um Bearer token. Como este backend não tem UI de sessão nenhuma (frontend é repositório separado), a correção é dupla: (1) usar `withBroadcasting($channels, ['middleware' => ['auth:sanctum']])` em vez do atalho, e (2) como `/broadcasting/auth` também não bate no prefixo `api/*`, o `shouldRenderJsonWhen` do handler de exceções precisou ser estendido para cobrir `broadcasting/*` também — sem isso, uma tentativa de auth sem token não vira um 401 limpo, vira um 500 (Laravel tenta redirecionar para uma rota `login` que não existe neste backend).

Canal privado `private-auction.{id}` (`routes/channels.php`) autoriza qualquer usuário autenticado — é uma casa de leilões pública, não uma negociação privada; a classificação de papel (vendedor/licitante/espectador) fica para o canal de presence da Fase 8.

`BidPlacedBroadcastEvent` (`bid.placed`) e `AuctionUpdatedBroadcastEvent` (`auction.updated`) são deliberadamente eventos separados — o primeiro é uma entrada de feed ("este lance aconteceu"), o segundo é um resync de estado resumido ("o leilão agora está assim"). Um cliente pode tratar os dois de forma completamente diferente (acrescentar ao feed vs. atualizar o cabeçalho). Ambos implementam `ShouldBroadcastNow`, não `ShouldBroadcast`: já são disparados de dentro do `BroadcastBidConsumer` (um processo de background reagindo ao RabbitMQ) — enfileirar de novo via Redis/Horizon só adicionaria latência sem benefício.

## Consequências

**Positivas**

- Provado ponta a ponta com um cliente WebSocket real (não Echo/pusher-js, um script mínimo falando o protocolo Pusher diretamente) — uma chamada REST de lance em um terminal produz `bid.placed` e `auction.updated` no cliente WS em outro, na casa de poucos milissegundos.
- Nenhuma conta de terceiro, nenhuma chave de API externa — todo o pipe roda dentro do `docker-compose` deste repositório.

**Negativas / trade-offs aceitos**

- Escala horizontal do Reverb (múltiplas instâncias) não está configurada — intencional para esta demo (documentado aqui para quando for necessário, não implementado prematuramente).
- Filas do RabbitMQ usadas pelos consumers são duráveis e persistem mensagens entre reinícios — rodar os consumers do `docker-compose` ao mesmo tempo que testes manuais ad-hoc (scripts, curl solto) acumula mensagens de teste na fila real; vale purgar (`docker compose exec rabbitmq rabbitmqctl purge_queue <fila>`, ou pela UI de management) antes de uma demonstração ao vivo para não competir com backlog antigo.
