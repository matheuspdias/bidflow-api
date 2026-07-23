# ADR-0018: Dashboard administrativo de negócio

## Status

Aceito

## Contexto

Depois de onze fases construindo o leilão em si, faltava uma visão de conjunto: quantos leilões em cada estado, quantos lances no total, quanto já foi efetivamente vendido, quantas pessoas estão olhando algo agora. Esse dado já existe inteiro no módulo `Auction` (contagens por status, soma de vendas fechadas, sets de presence do Redis desde a Fase 8) — faltava só uma pergunta e uma resposta.

## Decisão

`Shared\Domain\Contracts\BusinessMetricsLookup::current()` — mais um contrato no mesmo padrão da Fase 12 (`BidHistoryLookup` etc.): o módulo `Dashboard` pergunta, `BusinessMetricsLookupAdapter` (em `Auction`) responde, sem um depender dos internals do outro. A resposta é sempre um retrato completo recomputado na hora (contagem de leilões por status, total de lances, receita total, espectadores ao vivo somados por todos os leilões ativos) — não uma tabela de métricas mantida incrementalmente, porque nesta escala um punhado de `COUNT`/`SUM` agregados é mais simples e mais barato do que manter contadores sincronizados em paralelo com o resto do sistema.

Dois caminhos para o mesmo dado, como o resto do sistema já faz (Fase 9's `/live` vs. WS): `GET /api/dashboard/business` (nova ability `dashboard:read`, concedida a todo token como as demais) para a carga inicial da tela, e `BroadcastBusinessMetricsCommand` (`php artisan dashboard:broadcast-business`, processo próprio como o timer/closer) recalculando e transmitindo o mesmo retrato a cada 5 segundos em `dashboard.updated`, canal `private-dashboard`.

`private-dashboard` é um canal **privado**, não presence — ninguém precisa saber quem mais está olhando o dashboard, só os números. Qualquer usuário autenticado pode se inscrever, a mesma simplificação pragmática de `auction.{id}` (Fase 7): este sistema não tem um conceito de papel "admin" separado de "usuário comum" — todo token emitido no login/registro já recebe todas as abilities (`TokenAbility::all()`). Adicionar um papel de admin de verdade — e as decisões de modelagem que isso implica (quem promove alguém a admin, como isso se reflete no schema de `users`) — está fora do escopo desta fase; documentado aqui como a simplificação aceita, não um descuido.

## Consequências

**Positivas**

- Provado com um cliente WebSocket real: dois ticks de `dashboard.updated` cinco segundos depois um do outro, com os números certos.
- Nenhuma tabela nova, nenhum contador para manter sincronizado — só consultas agregadas sobre dados que já existem.

**Negativas / trade-offs aceitos**

- `live_viewers_total` faz uma chamada Redis (`SCARD`) por leilão ativo a cada tick — mesma característica de escala já aceita para `AuctionTimerBroadcastCommand` (ADR-0014): adequado ao tamanho desta demo, um leilão com milhares de leilões ativos simultâneos precisaria de outra estratégia.
- Qualquer usuário autenticado pode ver as métricas de negócio — aceitável para uma demo de portfólio, não para um sistema com usuários finais reais competindo entre si (aí, `dashboard:read` precisaria de fato ser restrito a um papel de admin, não concedido a todo mundo).
