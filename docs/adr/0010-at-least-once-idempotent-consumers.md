# ADR-0010: Entrega at-least-once e padrão de consumer idempotente

## Status

Aceito

## Contexto

AMQP (RabbitMQ) garante entrega **at-least-once**, nunca exactly-once: uma mensagem pode ser redelivered — se o consumer cair depois de processar mas antes de confirmar (`ack`), ou se a conexão cair no meio do processamento — sem que isso indique falha real no processamento anterior. Um consumer que assume "cada mensagem chega exatamente uma vez" está construindo sobre uma premissa falsa que eventualmente causa efeito duplicado (ex.: contar o mesmo lance duas vezes numa estatística).

## Decisão

**Idempotência por *claim* atômico, não por checar-e-agir.** A primeira versão desta base fazia "existe em `processed_events`? se não, processa e grava" — dois passos separados. Sob teste (duas instâncias do mesmo consumer, ou um redelivery correndo em paralelo com uma execução ainda em andamento — exatamente o que "escalar independentemente" deveria suportar), isso se mostrou uma race real: as duas checagens podiam passar antes de qualquer uma gravar, e ambas processavam. A correção: `claim()` tenta **inserir primeiro** em `processed_events` (chave única `(event_id, consumer_name)`) — só quem vence essa corrida de `INSERT` chama `process()`; a outra instância recebe uma violação de unicidade, interpreta como "não é meu, alguém já pegou ou já processou", e só dá `ack`. Se `process()` falhar depois do claim, o claim é liberado (`releaseClaim()`) para que o retry seguinte possa tentar de novo — sem isso, uma falha marcaria o evento como "processado" permanentemente, impedindo qualquer nova tentativa.

Esse `INSERT` de claim roda dentro do seu próprio `DB::transaction()` — necessário porque o Postgres (diferente do MySQL) aborta a transação corrente inteira após qualquer erro, não só a instrução que falhou; sem um savepoint próprio, uma violação de unicidade capturada em PHP ainda deixaria a conexão inutilizável para qualquer instrução seguinte seguinte na mesma transação (isso apareceu exatamente assim rodando os testes: instruções perfeitamente válidas começaram a falhar com "current transaction is aborted" depois do `INSERT` duplicado ser capturado sem savepoint).

**Retry com limite, depois dead-letter.** Uma falha de processamento (exceção lançada por `process()`) incrementa um header `x-retry-count` e republica a mensagem para reprocessamento; ao esgotar 3 tentativas, a mensagem é `nack`ada sem requeue, roteando para o dead-letter exchange (`domain_events.dlx`, declarado desde a Fase 5) em vez de ficar reprocessando para sempre ou se perder silenciosamente.

**Classe base genérica desde já.** `RabbitMqConsumerCommand` já expõe `recordMetrics()` como ponto de extensão vazio — a Fase 15 (dashboard técnico, contadores de eventos processados + latência) só precisa preencher esse método uma vez para que todo consumer existente e futuro ganhe a instrumentação, sem tocar em cada consumer individualmente.

**`PersistBidHistoryConsumer` é a prova do split CQRS.** `bid_history` é um read model desnormalizado, populado assincronamente pelo consumer — deliberadamente separado da tabela transacional `bids` (Fase 4, escrita síncrona dentro da mesma transação do lance). São dois modelos de dado para o mesmo fato, cada um otimizado para seu propósito: `bids` é a fonte de verdade transacional; `bid_history` existe para leitura eventualmente consistente (ex.: feeds, futuras queries de histórico da Fase 12) sem competir por lock com o caminho de escrita crítico da Fase 4.

**Cada consumer é seu próprio serviço Compose.** `UpdateAuctionStatsConsumer`, `PersistBidHistoryConsumer`, `SendBidNotificationConsumer` (corpo real na Fase 11) e `BroadcastBidConsumer` (corpo real na Fase 7) rodam em containers separados — escalar `bid-history-consumer` sob carga não implica escalar os outros três.

## Consequências

**Positivas**

- Reiniciar um consumer no meio do processamento (testado manualmente via `docker compose restart`) nunca duplica efeito — validado tanto manualmente quanto por teste automatizado (`tests/Feature/Auction/BidConsumerTest.php`, que simula redelivery publicando o mesmo `event_id` duas vezes deliberadamente).
- O mecanismo de retry+dead-letter é testado isoladamente (`Tests\Support\AlwaysFailingTestConsumer`) sem depender de derrubar um serviço de verdade — determinístico, não frágil.

**Negativas / trade-offs aceitos**

- `processed_events` cresce sem limpeza automática — aceitável na escala de um projeto de portfólio; um sistema de produção adicionaria expiração por idade.
- O retry via republish (em vez de esperar redelivery nativa do broker) significa que uma mensagem "em retry" tecnicamente é uma mensagem *nova* do ponto de vista do RabbitMQ, carregando o header incrementado — funcionalmente equivalente para o propósito de limitar tentativas, mas vale notar para quem for depurar via management UI (o delivery count nativo do RabbitMQ não reflete o retry count da aplicação).
