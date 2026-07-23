# ADR-0009: Redis + Horizon para jobs internos vs RabbitMQ para integration events

## Status

Aceito

## Contexto

O projeto usa duas infraestruturas de mensageria diferentes, instaladas desde a Fase 0 mas só agora ambas em uso real: filas Redis geridas pelo Horizon, e o exchange topic do RabbitMQ. É fácil, à primeira vista, perguntar "por que não só uma?" — a resposta é que resolvem problemas diferentes.

## Decisão

**Redis + Horizon** para *jobs internos* do próprio monólito: trabalho assíncrono que só interessa a este processo Laravel, com um único produtor e um único tipo de consumidor esperado (ex.: mais adiante, Fase 11, o e-mail de "leilão encerrado"). Ponto-a-ponto, uma fila, um worker.

**RabbitMQ** para *integration events*: fatos de domínio que **múltiplos consumers independentes** (Fase 6: estatísticas, histórico de lances, notificação, broadcast) precisam observar, cada um no seu próprio ritmo, sem que o publicador saiba quem são ou quantos são. Isso é exatamente o padrão pub/sub fan-out/topic — não fila ponto-a-ponto — por isso o exchange é `topic` (permite routing key seletivo por consumer) e por isso a escolha nunca foi usar `vladimir-yuldashev/laravel-queue-rabbitmq` (que trataria o RabbitMQ como só mais um driver de fila ponto-a-ponto do Laravel, o padrão errado para "N consumers ouvindo o mesmo fato").

Nunca se cruzam: um job Redis/Horizon nunca publica em RabbitMQ, e um consumer RabbitMQ nunca despacha para uma fila Redis — cada mecanismo fica no seu papel.

## Consequências

**Positivas**

- Cada mecanismo é usado para o problema que resolve melhor — nenhum dos dois força os dois pra fazer o trabalho do outro.
- Adicionar um novo consumer de um integration event (Fase 6+) nunca exige tocar no código que publica — é só mais um bind no exchange existente.

**Negativas / trade-offs aceitos**

- Duas infraestruturas de mensageria rodando (`redis` já existia para cache; `rabbitmq` é container adicional) — mais uma peça operacional do que um sistema com só uma fila. Aceito porque a UI de management do RabbitMQ (Fase 15: dashboard técnico) e a separação pub/sub de verdade valem essa complexidade extra num projeto que existe justamente para demonstrar arquitetura orientada a eventos.
