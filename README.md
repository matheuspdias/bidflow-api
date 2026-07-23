# BidFlow API

Backend de um sistema de leilão em tempo real, construído como projeto de portfólio para demonstrar **DDD + Clean Architecture + arquitetura orientada a eventos + bidding em tempo real via WebSocket** em Laravel.

O frontend (React) é um repositório separado — este projeto cobre apenas a API e a infraestrutura de eventos/WebSocket que a alimentam.

> Este README é escrito incrementalmente, fase a fase, junto com o código. Seções marcadas como pendentes serão preenchidas conforme o respectivo módulo é construído — veja o [índice de ADRs](#índice-de-adrs) e o histórico de commits para o estado exato de cada fase.

## Objetivo

Mostrar, em um sistema funcional de leilões com lances em tempo real, como estruturar um monólito modular em Laravel com:

- Módulos isolados por domínio de negócio (vertical slices), comunicando-se apenas através de contratos publicados no *shared kernel*.
- Separação de camadas por módulo (Domain / Application / Infrastructure / Presentation), com a camada de domínio livre de dependências do framework.
- Eventos de domínio traduzidos para eventos de integração, publicados em um message broker (RabbitMQ) e consumidos por processos independentes.
- Lances em tempo real via WebSocket (Laravel Reverb), com concorrência tratada por lock pessimista dentro de transações de banco.

## Stack

| Camada | Tecnologia |
|---|---|
| Linguagem / Framework | PHP 8.3+, Laravel (última versão estável) |
| Banco de dados | PostgreSQL 16 |
| Cache / filas internas | Redis + Horizon |
| Mensageria (integration events) | RabbitMQ (exchange topic, pub/sub) |
| WebSocket | Laravel Reverb |
| Autenticação | Laravel Sanctum (token, não cookie-SPA) |
| Testes | Pest (+ plugins Laravel, Arch, Faker) |
| Análise estática | Larastan / PHPStan (nível 6+) |
| Documentação de API | Scramble (OpenAPI 3.1 gerado a partir do código) |
| Infraestrutura local | Docker Compose (customizado, não Sail) |

Ver [ADR-0009](docs/adr/0009-redis-horizon-vs-rabbitmq.md) para a justificativa da separação entre filas internas (Redis/Horizon) e integration events (RabbitMQ).

## Arquitetura

*(Seção consolidada progressivamente; ver também os diagramas específicos linkados nas ADRs de cada fase.)*

### Estrutura de módulos

```
src/
├── Modules/{Auction,User,Notification,Dashboard,Auth}/
│   ├── Domain/          # Entities, Aggregates, Events, Exceptions, Repositories (interfaces), Services, ValueObjects
│   ├── Application/     # DTOs, UseCases, Commands, Queries
│   ├── Infrastructure/  # Persistence, Repositories (implementações), Listeners, Broadcast, Console/Consumers
│   ├── Presentation/    # Controllers, Requests, Resources
│   └── Providers/       # {Module}ServiceProvider.php
└── Shared/
    ├── Domain/          # ValueObjects, Events (contratos DomainEvent/IntegrationEvent), Contracts (UserIdentity, SellerLookup, BidderLookup)
    ├── Infrastructure/  # MessageBroker (RabbitMQ), Database (helpers de transação)
    └── Application/     # CommandBus/QueryBus (implementação própria, sem pacote de terceiros)
```

**Regra de fronteira entre módulos**: um módulo só pode depender de outro módulo através de contratos publicados em `Shared\Domain`, nunca das classes internas de outro módulo. Essa regra é garantida por um teste de arquitetura (`tests/Architecture/BoundariesTest.php`), rodado no CI.

`Bid` mora dentro de `Modules/Auction/Domain` como entidade filha do aggregate `Auction` — não é um módulo/aggregate próprio, já que não tem ciclo de vida independente de um leilão.

### Diagrama de camadas

Fluxo de dependência dentro de um módulo (Presentation → Application → Domain; Infrastructure → Domain). `Shared\Domain` é o único ponto de contato permitido entre módulos — nunca uma seta direta de `Modules\X` para `Modules\Y`.

```mermaid
flowchart TB
    subgraph ModuleA["Módulo (ex.: Auction)"]
        Presentation["Presentation<br/>Controllers · Requests · Resources"]
        Application["Application<br/>UseCases · Commands · Queries · DTOs"]
        Domain["Domain<br/>Aggregates · Entities · Events · ValueObjects<br/>Repositories (interfaces)"]
        Infrastructure["Infrastructure<br/>Eloquent Repositories · Listeners · Broadcast · Consumers"]

        Presentation --> Application
        Application --> Domain
        Infrastructure -.implementa.-> Domain
    end

    subgraph Shared["Shared kernel"]
        SharedDomain["Shared\\Domain<br/>ValueObjects (Money, DateRange, AggregateId)<br/>Contracts (UserIdentity, SellerLookup, BidderLookup,<br/>UserRegistrar, UserAuthenticator, TokenIssuer)<br/>Events (DomainEvent, IntegrationEvent)"]
        SharedInfra["Shared\\Infrastructure<br/>MessageBroker (RabbitMQ) · Database"]
        SharedApp["Shared\\Application<br/>CommandBus · QueryBus"]
    end

    Domain -->|só através de contratos| SharedDomain
    Application --> SharedApp
    Infrastructure --> SharedInfra

    ModuleB["Outro módulo (ex.: User)"] -.implementa contratos de.-> SharedDomain
```

`Shared\Domain` não depende de `Illuminate\*` nem de exceções genéricas — ver [ADR-0003](docs/adr/0003-shared-kernel-contracts.md) para o racional completo do shared kernel.

### Fluxo de lance

Ver [ADR-0006](docs/adr/0006-pessimistic-locking-bid-concurrency.md) (lock pessimista) e [ADR-0007](docs/adr/0007-bid-idempotency-strategy.md) (idempotência).

```mermaid
sequenceDiagram
    participant C as Cliente
    participant MW as EnsureIdempotentBidRequest
    participant UC as PlaceBidUseCase
    participant DB as Postgres (transação)

    C->>MW: POST /auctions/{id}/bids (Idempotency-Key)
    alt chave já vista para este bidder
        MW-->>C: resposta cacheada (mesma da 1ª vez)
    else chave nova
        MW->>UC: execute()
        UC->>DB: BEGIN + SELECT auctions FOR UPDATE
        Note over DB: concorrentes no mesmo leilão bloqueiam aqui até o commit
        UC->>UC: bidder blocked? seller? status ACTIVE? amount >= current+increment?
        alt alguma checagem falha
            UC->>DB: INSERT bid_audit_logs (rejected)
            UC->>DB: COMMIT
            UC-->>MW: throw DomainException
            MW-->>C: 403/422 (cacheado para replay)
        else aceito
            UC->>DB: INSERT bids
            UC->>DB: UPDATE auctions (current_value, highest_bid_id)
            UC->>DB: INSERT bid_audit_logs (accepted)
            UC->>DB: COMMIT
            UC->>UC: pullDomainEvents() → event(BidPlaced) (só depois do commit)
            UC-->>MW: BidPlacementResult
            MW-->>C: 201 (cacheado para replay)
        end
    end
```

- `Auction::placeBid()` valida três invariantes de domínio (status `ACTIVE`, vendedor não pode dar lance no próprio leilão, valor ≥ `current_value + minimum_increment`) — nenhuma delas toca framework ou banco.
- Toda rejeição grava uma linha em `bid_audit_logs` **dentro da mesma transação que é commitada** (não uma que sofre rollback) — ver ADR-0006 para o porquê disso exigir capturar a exceção dentro do closure da transação em vez de deixá-la propagar.
- Não existe rota para cancelar/editar um lance — garantido por teste (`tests/Feature/Auction/PlaceBidTest.php`).

### Fluxo de eventos (domain → integration → broadcast)

Ver [ADR-0008](docs/adr/0008-domain-vs-integration-events.md) (domain vs integration events) e [ADR-0009](docs/adr/0009-redis-horizon-vs-rabbitmq.md) (RabbitMQ vs Redis/Horizon).

```mermaid
flowchart LR
    A["Aggregate Auction<br/>record(DomainEvent)"] -->|"pullDomainEvents()<br/>(só depois do commit)"| B["event($domainEvent)"]
    B --> C["Infrastructure\\Listeners<br/>Publish*IntegrationEvent"]
    C -->|"fromDomainEvent()"| D["Infrastructure\\Events<br/>*IntegrationEvent"]
    D --> E["SafeIntegrationEventPublisher"]
    E -->|"sucesso"| F[("RabbitMQ<br/>exchange domain_events (topic)")]
    E -->|"falha"| G[("failed_integration_events<br/>(replay manual)")]
    F -.->|"Fase 6"| H["Consumers<br/>(stats, histórico, notificação, broadcast)"]
```

- Domain events (`Modules\Auction\Domain\Events`) só existem dentro do processo; integration events (`Modules\Auction\Infrastructure\Events`) são a tradução serializável que atravessa o RabbitMQ — nunca o caminho inverso.
- `php artisan rabbitmq:setup` declara o exchange topic `domain_events` e seu dead-letter exchange (`domain_events.dlx`) — idempotente, roda a cada deploy.
- Routing key: `{módulo}.{evento_snake_case}` (`auction.bid_placed`, `auction.auction_started`, `auction.auction_cancelled`).
- Falha ao publicar nunca reverte o lance nem propaga exceção — vira uma linha em `failed_integration_events` (ver ADR-0008).

### Estratégia WebSocket

Ver [ADR-0011](docs/adr/0011-reverb-websocket.md) (Reverb vs Pusher/Ably + história de escala via Redis) e [docs/websocket-events.md](docs/websocket-events.md) (payload de cada evento, atualizado a cada fase).

- Laravel Reverb (self-hosted, protocolo Pusher — compatível com `laravel-echo`/`pusher-js` sem modificação) em vez de Pusher/Ably (SaaS de terceiro).
- Canal privado `private-auction.{id}`: qualquer usuário autenticado pode se inscrever — é uma casa de leilões pública. Autenticação via `POST /broadcasting/auth`, que precisou de duas correções específicas para funcionar com token Sanctum em vez de sessão (ver ADR-0011): `withBroadcasting(..., ['middleware' => ['auth:sanctum']])` no lugar do atalho padrão, e `shouldRenderJsonWhen` estendido para cobrir `broadcasting/*` (sem isso, uma falha de auth vira 500 em vez de 401 — Laravel tenta redirecionar pra uma rota `login` que não existe neste backend).
- `bid.placed` (entrada de feed) e `auction.updated` (resync de estado resumido) são eventos deliberadamente separados, ambos disparados pelo `BroadcastBidConsumer` reagindo ao integration event `auction.bid_placed` — fora do ciclo de vida da request HTTP que criou o lance.
- Validado ponta a ponta com um cliente WebSocket real (protocolo Pusher puro, sem Echo): uma chamada REST de lance produz os dois eventos no cliente WS em poucos milissegundos.

## Modelo de domínio

Ver [ADR-0005](docs/adr/0005-auction-lifecycle.md) para o racional completo do ciclo de vida do leilão.

```
SCHEDULED ──activate()──▶ ACTIVE ──(Fase 11)──▶ CLOSED
    │                        │
    └──────cancel()──────────┘
                │
                ▼
           CANCELLED
```

- Só em `SCHEDULED` o leilão pode ser editado (`updateDetails()`); preço inicial, incremento mínimo, `buy_now_price` e `reserve_price` são fixados na criação e nunca mudam depois.
- `CANCELLED`/`CLOSED` são estados terminais — não existe "deletar" um leilão, só cancelar (preserva histórico e integridade referencial).
- `CLOSED` só é alcançado pelo scheduler de encerramento (Fase 11), nunca por uma rota HTTP direta.
- `Auction::placeBid()` (Fase 4) valida status `ACTIVE`, impede o vendedor de dar lance no próprio leilão, e exige `amount >= current_value + minimum_increment` — `Bid` é uma entidade de `Auction`, não um módulo próprio (ver ADR-0001).
- `Auction::isOwnedBy(UserIdentity)` é o único ponto de checagem de propriedade, usado tanto na autorização de edição (Fase 3) quanto — via `bidderId === sellerId` diretamente em `placeBid()` — na regra "vendedor não pode dar lance no próprio leilão".

### Endpoints de leilão

```
GET    /api/categories                    (público)
GET    /api/auctions                      (público, paginado, filtros ?status= ?category_id=)
GET    /api/auctions/{id}                 (público)
POST   /api/auctions                      (auth, ability auction:manage)
PATCH  /api/auctions/{id}                 (auth, dono, só enquanto SCHEDULED)
POST   /api/auctions/{id}/activate        (auth, dono)
POST   /api/auctions/{id}/cancel          (auth, dono)
POST   /api/auctions/{id}/bids            (auth, ability bid:place, header Idempotency-Key obrigatório)
```

`POST .../bids` também passa por um rate limit nomeado (`bid-placement`, 20/min por usuário) e nunca tem um par PATCH/DELETE — lances não são editáveis nem canceláveis.

## Fluxo de Auth

Autenticação via **token Sanctum** (não cookie-SPA) — ver [ADR-0004](docs/adr/0004-auth-token-sanctum.md) para o racional completo.

```
POST /api/register  { name, email, password, password_confirmation } → 201 { user, token }
POST /api/login      { email, password }                              → 200 { user, token }
POST /api/logout                                    (Bearer token)    → 204
GET  /api/me                                         (Bearer token)   → 200 { data: user }
```

- Todo endpoint autenticado usa `auth:sanctum` + um middleware `abilities:<ability>` checando a habilidade do token.
- Vocabulário de abilities fixado nesta fase (`App\Modules\Auth\Domain\ValueObjects\TokenAbility`): `bid:place`, `profile:read`, `profile:write`, `auction:manage`, `notifications:read`. Login/registro emitem um token com todas as abilities; tokens mais restritos (ex.: uma integração só-para-lances) poderão ser emitidos depois sem precisar mudar esse vocabulário.
- Rate limit nomeado `login` (5 tentativas/minuto por `ip+email`) aplicado a `/register` e `/login`.
- Endpoints de histórico de lances/leilões ganhos/perdidos/ranking (`/api/profile/bids`, `/api/profile/auctions/won`, `/api/profile/auctions/lost`, `/api/rankings`) existem já como **stub**, retornando `{ data: [] }` — implementação real na Fase 12.

## Contrato da tela de leilão ao vivo

*(pendente — Fase 9)*

## Metodologia dos rankings

*(pendente — Fase 12)*

## Métricas do dashboard admin

*(pendente — Fase 14)*

## Dashboard técnico

*(pendente — Fase 15)*

## Rodando via Docker

Pré-requisitos: Docker + Docker Compose, nada mais — PHP e Composer rodam dentro dos containers.

```bash
git clone <repo> bidflow-api
cd bidflow-api
cp .env.example .env

# build + sobe todos os serviços
docker compose up -d --build

# instala dependências e gera a chave da aplicação
docker compose exec app composer install
docker compose exec app php artisan key:generate

# roda as migrations
docker compose exec app php artisan migrate

# expõe o disco público (avatares de usuário, fotos de leilão)
docker compose exec app php artisan storage:link

# roda a suíte de testes
docker compose exec app php artisan test

# roda a análise estática
docker compose exec app ./vendor/bin/phpstan analyse
```

A API fica disponível em `http://localhost:8000` (porta configurável via `APP_PORT` no `.env`).

### Serviços do Docker Compose

| Serviço | Papel | Porta local |
|---|---|---|
| `app` | PHP-FPM 8.3, roda a aplicação | — (interno, via nginx) |
| `nginx` | Servidor web, proxy para o `app` | `8000` |
| `postgres` | Banco de dados principal | `5433` |
| `redis` | Cache, filas internas (Horizon), read models | `6380` |
| `rabbitmq` | Broker de integration events + UI de management | `5672` / `15672` |
| `reverb` | Servidor WebSocket (Laravel Reverb) | `8080` |
| `horizon` | Worker das filas Redis (jobs internos, ex.: e-mail) | — |
| `auction-stats-consumer` | Consumer RabbitMQ: estatísticas de lance (Redis) | — |
| `bid-history-consumer` | Consumer RabbitMQ: read model `bid_history` | — |
| `bid-notification-consumer` | Consumer RabbitMQ: notificação de outbid (stub — Fase 11) | — |
| `bid-broadcast-consumer` | Consumer RabbitMQ: broadcast WebSocket (stub — Fase 7) | — |

> O loop de broadcast do timer (Fase 10) será adicionado como serviço próprio quando introduzido.

### Topologia RabbitMQ

Ver [ADR-0010](docs/adr/0010-at-least-once-idempotent-consumers.md) para o racional completo (entrega at-least-once, idempotência, retry+dead-letter).

| Exchange/Fila | Tipo | Routing key | Propósito |
|---|---|---|---|
| `domain_events` | topic | — | Exchange único para todos os integration events do sistema |
| `domain_events.dlx` | fanout | — | Dead-letter exchange — destino final após retries esgotados |
| `domain_events.update_auction_stats` | fila | `auction.bid_placed` | Incrementa contadores de lance no Redis |
| `domain_events.persist_bid_history` | fila | `auction.bid_placed` | Popula o read model `bid_history` (CQRS, separado da tabela transacional `bids`) |
| `domain_events.send_bid_notification` | fila | `auction.bid_placed` | Notifica o lance superado — corpo real na Fase 11 |
| `domain_events.broadcast_bid` | fila | `auction.bid_placed` | Broadcast WebSocket — corpo real na Fase 7 |

Cada consumer declara sua própria fila (durável, com `x-dead-letter-exchange` apontando para `domain_events.dlx`) e é idempotente via a tabela `processed_events` (chave `event_id` + nome do consumer) — uma mensagem redelivered (reinício do consumer, falha de rede antes do `ack`) nunca produz efeito duplicado.

## Estrutura de testes

- `tests/Unit` — testes unitários isolados.
- `tests/Feature` — testes de ponta a ponta via HTTP, rodando contra Postgres real (`bidflow_testing`), não SQLite — necessário desde já porque os testes de concorrência de lances (Fase 4) dependem de locking real do Postgres (`SELECT ... FOR UPDATE`).
- `tests/Architecture` — regras estruturais via `pestphp/pest-plugin-arch`: fronteiras de módulo (nenhum módulo acessa classes internas de outro) e camada `Domain` de **cada** módulo (não só `Shared`) livre de dependência de `Illuminate\*` e de exceções genéricas (`Exception`/`RuntimeException`).
- `tests/Concurrency` — testes que forjam concorrência real de SO (`pcntl_fork`), não simulada dentro de um único processo. Deliberadamente **fora** de `RefreshDatabase` (cada processo forkado precisa enxergar dados já commitados por outra sessão de banco) — cada teste confirma e limpa seus próprios dados manualmente. Ver ADR-0006.

> **Atenção ao rodar os testes de consumer localmente**: o banco de testes é isolado (`bidflow_testing`), mas o RabbitMQ **não** — os testes em `tests/Feature/Auction/BidConsumerTest.php` usam as mesmas filas (`domain_events.*`) que os serviços consumer do `docker-compose` (`auction-stats-consumer`, `bid-history-consumer`, etc.). Rodar a suíte com esses serviços ativos faz duas instâncias do mesmo consumer competirem pela mesma mensagem — o comportamento correto (só uma processa, ver ADR-0010), mas os testes esperam controlar exatamente quantas mensagens cada fila recebe, então os containers e a suíte acabam brigando pela mesma fila. Pare os consumers antes de rodar os testes: `docker compose stop auction-stats-consumer bid-history-consumer bid-notification-consumer bid-broadcast-consumer`.

## Índice de ADRs

| ADR | Título |
|---|---|
| [0001](docs/adr/0001-monolito-modular.md) | Monólito modular com vertical slices |
| [0002](docs/adr/0002-clean-architecture-por-modulo.md) | Camadas de Clean Architecture por módulo |
| [0003](docs/adr/0003-shared-kernel-contracts.md) | Padrão de contrato do shared kernel |
| [0004](docs/adr/0004-auth-token-sanctum.md) | Autenticação por token Sanctum (não cookie-SPA) |
| [0005](docs/adr/0005-auction-lifecycle.md) | Ciclo de vida do leilão (state machine) |
| [0006](docs/adr/0006-pessimistic-locking-bid-concurrency.md) | Lock pessimista para concorrência de lances |
| [0007](docs/adr/0007-bid-idempotency-strategy.md) | Estratégia de idempotency key para lances |
| [0008](docs/adr/0008-domain-vs-integration-events.md) | Domain events vs integration events |
| [0009](docs/adr/0009-redis-horizon-vs-rabbitmq.md) | Redis + Horizon (jobs internos) vs RabbitMQ (integration events) |
| [0010](docs/adr/0010-at-least-once-idempotent-consumers.md) | Entrega at-least-once e padrão de consumer idempotente |
| [0011](docs/adr/0011-reverb-websocket.md) | Laravel Reverb para WebSocket |

*(demais ADRs adicionadas conforme as fases avançam)*
