# ADR-0008: Domain events vs integration events

## Status

Aceito

## Contexto

O shared kernel (Fase 1) já define duas interfaces distintas — `DomainEvent` e `IntegrationEvent` — mas até a Fase 4 só a primeira tinha implementações reais (`AuctionStarted`, `AuctionCancelled`, `BidPlaced`). Esta fase é onde a segunda passa a existir de verdade, e onde a fronteira entre as duas precisa ficar inequívoca — é fácil, sem essa disciplina, deixar o domínio "vazar" para fora do processo sem perceber.

## Decisão

**Domain event** (`App\Modules\Auction\Domain\Events\*`) é um fato que já aconteceu dentro do aggregate, no vocabulário do domínio — `BidPlaced` carrega um `Money`, não uma string; não tem `event_id` (não faz sentido para algo que nunca sai do processo). Levantado via `pullDomainEvents()` e despachado pelo *use case* só **depois do commit** da transação que o produziu (ver ADR-0006) — nunca antes, e nunca pela camada de domínio diretamente.

**Integration event** (`App\Modules\Auction\Infrastructure\Events\*`) é a mesma informação, traduzida para um formato que atravessa o processo: `event_id` (UUID, para dedupe do lado do consumer — Fase 6), campos primitivos serializáveis (`amount` vira string decimal + `currency`, não o Value Object `Money`), e uma `routingKey()` própria. Constrói-se a partir de um domain event via um named constructor (`fromDomainEvent()`), nunca o contrário — a tradução é sempre unidirecional, domínio → integração.

**A tradução mora em `Infrastructure\Listeners`, não em `Domain`.** Cada listener (`PublishBidPlacedIntegrationEvent`, etc.) escuta um `DomainEvent` específico via `Event::listen()` (registrado no `{Módulo}ServiceProvider::boot()`), constrói o `IntegrationEvent` correspondente, e publica através de `SafeIntegrationEventPublisher`. Colocar essa tradução dentro do aggregate ou de um use case acoplaria o domínio a uma decisão de infraestrutura (formato de serialização, biblioteca de mensageria) que pode mudar sem que nenhuma regra de negócio tenha mudado — exatamente o tipo de acoplamento que a Clean Architecture (ADR-0002) existe para evitar.

**Publish nunca lança exceção para quem chamou.** `SafeIntegrationEventPublisher` captura qualquer falha do `RabbitMqPublisher`, grava em `failed_integration_events` (para replay manual futuro) e loga — nunca deixa a falha propagar. Isso é o que garante que uma falha de broker jamais reverte um lance já commitado.

Convenção de routing key: `{módulo}.{evento_em_snake_case}` — `auction.bid_placed`, `auction.auction_started`, `auction.auction_cancelled`. Um único exchange topic (`domain_events`, declarado por `php artisan rabbitmq:setup`) recebe todos os eventos de integração do sistema; consumers (Fase 6) fazem bind seletivo por padrão de routing key.

## Consequências

**Positivas**

- Trocar de RabbitMQ para outro broker no futuro tocaria só `Shared\Infrastructure\MessageBroker` e as classes `Infrastructure\Events`/`Infrastructure\Listeners` de cada módulo — nunca o aggregate ou os use cases.
- `AuctionStarted`/`AuctionCancelled` já publicam de verdade nesta fase, mesmo sem nenhum consumer real ainda os processando (isso é a Fase 6) — a Fase 6 só precisa escrever consumers, não revisitar o lado de publish.
- Testado ponta a ponta sem mock: `tests/Feature/Auction/IntegrationEventPublishingTest.php` declara uma fila temporária no RabbitMQ real do `docker-compose`, dispara a ação via HTTP, e consome a mensagem de volta — prova que o pipeline funciona, não só que o código "parece certo".

**Negativas / trade-offs aceitos**

- Cada domain event precisa de uma classe de integration event e um listener correspondentes — alguma duplicação estrutural (3 domain events, 3 integration events, 3 listeners), aceita em troca de cada tradução ser explícita e testável isoladamente em vez de um mapeamento genérico via reflection.
