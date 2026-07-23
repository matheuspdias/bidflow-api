# ADR-0019: Dashboard técnico

## Status

Aceito

## Contexto

O dashboard de negócio (Fase 14) responde "como vai o negócio". Faltava a pergunta complementar, de operação: as filas do RabbitMQ estão se acumulando (um consumer parado, ou mais lento que a chegada de eventos)? Quantas conexões WebSocket o Reverb está segurando agora? Cada consumer está processando com que latência? Diferente da Fase 14, nenhum desse dado é "dado de negócio" de um módulo específico — é estado da própria infraestrutura compartilhada (RabbitMQ, Reverb, os contadores que cada consumer já grava no Redis).

`RabbitMqConsumerCommand::recordMetrics()` existia como método vazio desde a Fase 6, comentado exatamente como "o lugar onde a Fase 15 vai adicionar isso" — chegou a hora de preenchê-lo.

## Decisão

**`recordMetrics()` implementado**: dois contadores Redis por consumer — `processed_count` (incrementado) e `total_latency_ms` (somado a cada evento, `agora - occurred_at` em milissegundos). Sem lista de amostras, sem percentis — o dashboard divide um pelo outro para uma média corrida. Suficiente para responder "esse consumer está acompanhando o ritmo", não uma stack de observabilidade completa.

**Profundidade de fila via API HTTP do RabbitMQ**: o plugin de management já vem habilitado (`rabbitmq:3.13-management-alpine`, desde a Fase 0) — `GET /api/queues/{vhost}/{fila}` com Basic Auth (mesmas credenciais do broker) devolve `messages_ready`, `messages_unacknowledged`, `consumers`. Nenhuma infraestrutura nova.

**Conexões WebSocket via API HTTP do Reverb**: `GET /apps/{app_id}/connections` (Pusher-compatible, o pacote `pusher-php-server` que o Laravel já usa para broadcasting cuida da assinatura HMAC automaticamente). Isolado na sua própria classe, `WebSocketConnectionsCounter` — não porque a lógica precisasse, mas porque o cliente HTTP do `pusher-php-server` usa seu próprio Guzzle interno, invisível para `Http::fake()` (que só intercepta o cliente do `Illuminate\Http\Client`). Testes trocam essa classe inteira por uma fake via *container binding*, em vez de tentar simular um Reverb de verdade.

Nenhum contrato novo em `Shared\Domain\Contracts`, diferente da Fase 14: todo dado aqui é infraestrutura compartilhada, não dado de negócio de outro módulo — `TechnicalMetrics` (módulo `Dashboard`) fala direto com Redis, a API do RabbitMQ e `WebSocketConnectionsCounter`, sem intermediar através de nenhum módulo de domínio.

Mesmo par de caminhos do resto do sistema: `GET /api/dashboard/technical` (mesma ability `dashboard:read` da Fase 14) e `BroadcastTechnicalMetricsCommand` transmitindo o mesmo retrato a cada 5s em `technical.updated`, canal privado `dashboard-technical` — separado de `dashboard` (Fase 14) só porque são audiências conceitualmente diferentes (operação vs. negócio), não porque o acesso seja hoje restrito de forma diferente (mesma simplificação da Fase 14 — nenhum papel de admin existe ainda).

A lista de filas/consumers monitorados é uma constante fixa dentro de `TechnicalMetrics` — não há registro central de onde derivá-la sem instanciar cada classe consumer; curta o bastante para manter a mão, sincronizada manualmente quando um consumer novo aparecer.

## Consequências

**Positivas**

- Provado com infraestrutura real, não mockada: a contagem de filas veio de mensagens de verdade acumuladas nas filas (os consumers estavam parados durante os testes desta sessão) via a API de management real, e a contagem de conexões foi de 0 para 1 no instante exato em que um cliente WebSocket real se conectou, confirmado por dois ticks de `technical.updated` cinco segundos apart.
- `recordMetrics()` sendo implementado na classe-base significa que todo consumer already existente ganhou instrumentação sem precisar tocar em nenhum deles individualmente — exatamente o que a Fase 6 previu ao criar o método vazio.

**Negativas / trade-offs aceitos**

- Uma chamada HTTP por fila a cada tick (8 filas, a cada 5s) — aceitável nesta escala, mas é tráfego HTTP real contra o RabbitMQ a cada tick, não gratuito.
- `total_latency_ms` cresce indefinidamente (nunca é resetado) — a média corrida vai suavizando qualquer pico conforme `processed_count` cresce, o que é aceitável para "está saudável agora" mas esconde uma degradação recente atrás de um histórico longo o bastante. Um dashboard de produção de verdade precisaria de uma janela de tempo, não um total desde sempre.
