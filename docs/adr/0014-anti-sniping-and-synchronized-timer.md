# ADR-0014: Anti-sniping e timer sincronizado

## Status

Aceito

## Contexto

Um leilão que termina no segundo exato de um `ends_at` fixo é vulnerável a *sniping*: um lance no último instante, sem chance de resposta de quem estava vencendo. E mesmo sem sniping, uma tela contando "tempo restante" só a partir do `ends_at` que já tem (Fase 3) sofre do drift normal de relógio entre cliente e servidor — inofensivo com uma hora restante, decisivo nos últimos segundos.

## Decisão

**Anti-sniping** vive no agregado, não no use case: `Auction::extendIfWithinAntiSnipingWindow(now, windowSeconds, extensionSeconds, maxExtensions)` — chamado por `PlaceBidUseCase` logo após `placeBid()` aceitar o lance, dentro da mesma transação (nunca para um lance rejeitado). Um lance chegando a `windowSeconds` ou menos de `ends_at` empurra o fim para frente em `extensionSeconds`, incrementa `extensions_count` (nova coluna) e registra `AuctionExtended` — até `maxExtensions` vezes por leilão, para que um bidder não consiga prolongar indefinidamente dando lance sempre no último segundo de cada extensão. Valores default (`config/auctions.php`, sobrepostos por `ANTI_SNIPING_*` no `.env`): janela de 120s, extensão de 120s, máximo de 5 extensões.

`AuctionExtended` segue o mesmo caminho de todo domain event deste sistema — `AuctionExtendedIntegrationEvent` (routing key `auction.auction_extended`) → `BroadcastAuctionExtendedConsumer` (fila e serviço próprios) → `auction.extended` no canal de presence. Nenhum atalho aqui: o mesmo pipeline de `BidPlaced`/`AuctionStarted`, por consistência com o resto do sistema.

**Timer sincronizado** é deliberadamente uma coisa diferente de anti-sniping — não reage a eventos, é um relógio. `AuctionTimerBroadcastCommand` (`php artisan auctions:broadcast-timer`, processo próprio no `docker-compose`, como Reverb/Horizon) roda um loop de 1 segundo (configurável via `--interval`) e, a cada tick, busca leilões `ACTIVE` com `ends_at` dentro de `config('auctions.timer.broadcast_window_seconds')` (default 300s) — não todo leilão ativo do sistema — e transmite `timer.updated` com `seconds_remaining` para cada um. A janela existe porque sincronização só importa perto do fim: um leilão com seis horas restantes não precisa de um tick por segundo, o cliente calcula sozinho a partir do `ends_at` que já tem. A query usa o model Eloquent diretamente, não `AuctionRepository` — nenhuma regra de negócio do agregado (Money, DateRange, validação de lance) é necessária só para ler um `id` e um `ends_at` a cada segundo, e hidratar o agregado inteiro nesse ritmo seria desperdício.

## Consequências

**Positivas**

- Provado ponta a ponta com um cliente WebSocket real: contagem regressiva visível decrescendo segundo a segundo (`20, 19, 18, ...`) a partir do `timer.updated` transmitido pelo comando rodando de verdade no `docker-compose`.
- `extendIfWithinAntiSnipingWindow()` testado nos quatro cantos (dentro da janela, fora da janela, `ends_at` já no passado, teto de extensões atingido) isoladamente no agregado, sem precisar de HTTP/banco — e depois de novo via `PlaceBidUseCase` num teste de Feature completo, incluindo o integration event chegando ao consumer real.

**Negativas / trade-offs aceitos**

- `AuctionTimerBroadcastCommand` varre todos os leilões ativos dentro da janela a cada tick — para poucas dezenas de leilões simultâneos na janela isso é irrelevante; numa escala muito maior valeria substituir por algo orientado a evento (ex.: agendar um timer por leilão só quando ele entra na janela), não implementado agora por ser complexidade prematura para o tamanho desta demo.
- Mais um serviço de `docker-compose` (`auction-timer`) rodando indefinidamente mesmo quando nenhum leilão está perto do fim — aceitável pelo mesmo motivo que Reverb e Horizon já rodam assim.
