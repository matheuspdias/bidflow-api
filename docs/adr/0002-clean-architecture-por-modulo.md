# ADR-0002: Clean Architecture por módulo

## Status

Aceito

## Contexto

Definido o corte vertical por módulo (ADR-0001), falta decidir como organizar o código *dentro* de cada módulo. O objetivo do projeto inclui demonstrar Clean Architecture — ou seja, regras de negócio que não dependem de detalhes de infraestrutura (framework, banco, broker de mensagens), e que poderiam, em tese, ser testadas e reutilizadas independentemente deles.

## Decisão

Cada módulo é dividido internamente em quatro camadas, com a regra de dependência apontando sempre para dentro (Presentation → Application → Domain; Infrastructure → Domain):

- **Domain** (`Domain/Entities`, `Aggregates`, `Events`, `Exceptions`, `Repositories`, `Services`, `ValueObjects`): regras de negócio puras. `Repositories` aqui contém apenas **interfaces** — o contrato do que o domínio precisa persistir, não como. Sem dependência de `Illuminate\*`; essa restrição é verificada pelo teste de arquitetura para `Shared\Domain` desde a Fase 0 e estendida ao domínio de cada módulo conforme ganham classes.
- **Application** (`DTOs`, `UseCases`, `Commands`, `Queries`): orquestra o domínio para atender um caso de uso específico (ex.: `PlaceBidUseCase`). Não contém regra de negócio própria — decide *quando* chamar o domínio, valida pré-condições de nível de aplicação (autenticação, autorização), gerencia transação.
- **Infrastructure** (`Persistence`, `Repositories`, `Listeners`, `Broadcast`, `Console/Consumers`): implementações concretas dos contratos do domínio — `EloquentAuctionRepository` implementando `AuctionRepository`, listeners que traduzem domain events em integration events, consumers de fila.
- **Presentation** (`Controllers`, `Requests`, `Resources`): a borda HTTP. Controllers são deliberadamente finos — só traduzem request → use case → resource.

Cada módulo expõe seu próprio `{Módulo}ServiceProvider`, registrado em `bootstrap/providers.php`, responsável por fazer o *binding* das interfaces de `Domain\Repositories` para as implementações de `Infrastructure\Repositories`.

## Consequências

**Positivas**

- O aggregate `Auction` (Fase 3+) pode ser testado unitariamente sem tocar em banco de dados, HTTP ou fila — suas invariantes (transições de status, validação de lance, cap de extensão de anti-sniping) são só PHP puro.
- Trocar Eloquent por outra estratégia de persistência, ou RabbitMQ por outro broker, afeta apenas a camada `Infrastructure` do módulo em questão.
- A pasta `Repositories` existir tanto em `Domain` (interface) quanto em `Infrastructure` (implementação) torna a inversão de dependência explícita na própria estrutura de pastas, sem precisar ler código para descobrir.

**Negativas / trade-offs aceitos**

- Mais indireção do que um CRUD tradicional Laravel (model + controller): um caso de uso simples ainda passa por Controller → UseCase → Aggregate → Repository interface → implementação Eloquent. Aceito porque o objetivo do projeto é justamente demonstrar essa separação, não minimizar boilerplate.
- Especificamente para `Bid` (ADR-0001), a estrutura de camadas se aplica através do aggregate `Auction`, não de uma estrutura própria — não há `Domain/Entities/Bid` com Application/Infrastructure/Presentation próprios, pois `Bid` não é acessado fora do ciclo de vida de `Auction`.
