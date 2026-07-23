# CLAUDE.md

Instruções de projeto para o BidFlow API — um backend Laravel de leilão em tempo real, projeto de portfólio demonstrando DDD + Clean Architecture + arquitetura orientada a eventos + WebSocket. Todas as 17 fases do plano original (Fase 0–16) já foram implementadas, testadas e documentadas — veja [docs/architecture-walkthrough.md](docs/architecture-walkthrough.md) para uma leitura guiada do sistema inteiro e `docs/adr/` para o porquê de cada decisão. Este arquivo é sobre **como trabalhar neste repositório daqui pra frente**, não sobre o que ele faz.

## Fluxo de trabalho para qualquer feature nova

Para qualquer pedido não-trivial (nova feature, novo endpoint, nova fase), seguir esta sequência, sempre nesta ordem:

1. **Implementar** seguindo as regras de arquitetura abaixo.
2. **Testar** (Pest) — unit para regras de domínio isoladas, feature para o caminho HTTP/consumer completo.
3. **Análise estática**: `docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M` — nível 6, zero erros antes de seguir.
4. **Validar de verdade**, não só os testes automatizados: quando a feature envolve WebSocket, RabbitMQ ou Redis, rodar um smoke test contra a infraestrutura real (containers do `docker compose`), não só mocks. Ver seção "Testando contra infraestrutura real" abaixo.
5. **Documentar**: atualizar a seção relevante do `README.md`, e escrever uma nova ADR em `docs/adr/NNNN-slug.md` (próximo número sequencial) sempre que a mudança envolver uma decisão arquitetural real — não para todo bugfix, mas para toda escolha que alguém lendo o projeto depois vá querer saber o porquê.
6. **Commitar** — ver política de commit abaixo.

Não pular etapas mesmo em tarefas que pareçam pequenas — este projeto existe para ser lido, não só para funcionar.

## Política de commit

- Commitar ao final de cada feature/fase concluída, como se o usuário tivesse escrito o commit.
- **Nunca** incluir `Co-Authored-By: Claude` ou qualquer trailer de atribuição de IA — isso substitui o comportamento padrão do Claude Code para este repositório especificamente.
- Mensagem de commit em português, no mesmo estilo dos 17 commits já existentes (`git log --oneline` para referência): título curto descrevendo o que foi entregue, corpo explicando o porquê das decisões não óbvias, nunca uma lista mecânica de arquivos alterados.
- Só commitar quando explicitamente pedido ou quando o fluxo de trabalho acima chegar nessa etapa — não commitar no meio de uma investigação.

## Regras de arquitetura (garantidas por `tests/Architecture/BoundariesTest.php`)

- **Fronteira de módulo**: um módulo (`Auction`, `User`, `Notification`, `Dashboard`, `Auth`) só pode depender de outro através de contratos publicados em `Shared\Domain\Contracts` — nunca de classes internas de outro módulo. Quando o módulo A precisa de um dado que pertence ao módulo B, criar um contrato em `Shared\Domain\Contracts`, implementá-lo como adapter em B, injetar em A.
- **Pureza da camada Domain**: `Shared\Domain` e o `Domain` de cada módulo não podem depender de `Illuminate\*` nem lançar `Exception`/`RuntimeException` genéricas. Domain é o único lugar onde regra de negócio pode viver sem nenhuma referência a framework.
- **Camadas por módulo**: `Domain` (Aggregates/Entities/Events/Exceptions/Repositories-interfaces/ValueObjects) → `Application` (UseCases/DTOs) → `Infrastructure` (Eloquent, Listeners, Broadcast, Console/Consumers, Adapters) → `Presentation` (Controllers/Requests/Resources), mais `Providers/{Módulo}ServiceProvider.php`.
- `Bid` é entidade filha de `Auction`, não um agregado/módulo próprio — mesmo raciocínio vale para qualquer conceito sem ciclo de vida independente do seu agregado pai; não criar um módulo novo só porque um conceito tem nome próprio.
- Processos de fundo que só fazem broadcast (timer, closer, dashboards) **não** passam por `RabbitMqConsumerCommand` — só consumers de verdade (que reagem a um integration event) usam essa base. Um "relógio" (loop com `sleep()`) é uma classe `Illuminate\Console\Command` direta, com `--iterations` para ser testável sem loop infinito.

## Testando contra infraestrutura real

Sempre que a feature tocar WebSocket (Reverb), RabbitMQ ou Redis além do que os testes automatizados já cobrem, validar com um smoke test real antes de considerar a feature pronta:

- **Fixture rápida**: `docker compose exec app php artisan tinker --execute='...'` para criar usuário/leilão/token de teste.
- **Cliente WebSocket**: scripts em `/tmp/claude-1000/.../scratchpad/presence-client.mjs` e `private-channel-client.mjs` (protocolo Pusher puro, sem Echo) já existem para isso — reusar ou adaptar em vez de escrever do zero.
- **Limpar depois**: apagar os registros de teste via tinker ao final (`Model::query()->delete()`); não deixar dado de smoke test manual no banco de dev.

### Cuidado com os consumers do RabbitMQ durante testes

Os serviços consumer do `docker compose` (`auction-stats-consumer`, `bid-history-consumer`, `bid-notification-consumer`, `bid-broadcast-consumer`, `viewer-count-consumer`, `auction-extended-consumer`, `auction-ended-consumer`, `auction-won-notification-consumer`, mais os "relógios" `auction-timer`, `auction-closer`, `dashboard-business-broadcaster`, `dashboard-technical-broadcaster`) usam filas duráveis. Rodar a suíte de testes com esses containers ativos faz o container real competir com o teste pela mesma mensagem, produzindo falhas não-determinísticas (o container consome antes do teste, ou vice-versa).

**Sempre**, antes de rodar `php artisan test`:
```bash
docker compose stop auction-stats-consumer bid-history-consumer bid-notification-consumer bid-broadcast-consumer viewer-count-consumer auction-extended-consumer auction-timer auction-closer auction-ended-consumer auction-won-notification-consumer dashboard-business-broadcaster dashboard-technical-broadcaster
```
E religar todos depois de terminar (`docker compose start <mesma lista>`) — o estado esperado do ambiente é com todos os 19 serviços rodando.

### Isolamento de dados nos testes

- Postgres: `bidflow_testing`, banco separado de `bidflow` (dev) — configurado em `phpunit.xml`.
- Redis: `REDIS_DB=1` nos testes, separado do `0` que dev/produção usam (`phpunit.xml`) — antes disso, uma chave dinâmica por id auto-incrementado podia colidir com sobra de sessão manual de dev (ver ADR-0017). Se escrever um teste que usa `Redis::` diretamente com uma chave derivada de um id de banco, não presumir que o Redis está vazio — mas também não precisa mais se preocupar com colisão contra dados de dev graças a esse isolamento.
- Sanctum em testes: `Laravel\Sanctum\Sanctum::actingAs($user, ['*'])`, não `$this->actingAs($user, 'sanctum')`.
- `tests/Concurrency` deliberadamente não usa `RefreshDatabase` (processos forkados via `pcntl_fork` precisam ver dados já commitados por outra sessão de banco).

## Documentação

- **ADR por decisão arquitetural real** (`docs/adr/NNNN-slug.md`, numeração sequencial, nunca reaproveitar número): contexto, decisão, consequências (positivas e negativas/trade-offs aceitos) — seguir o formato dos 19 ADRs existentes.
- **README.md**: atualizado incrementalmente — nova seção ou extensão de seção existente a cada feature, nunca deixado "pendente" depois que a feature está pronta (checar se não sobrou nenhuma referência a "pendente"/"Fase futura" que já foi resolvida).
- **docs/websocket-events.md**: toda vez que um evento WebSocket novo (ou canal novo) é adicionado, documentar aqui com o payload exato — é a referência viva de todo evento do sistema.
- **docs/openapi.yaml**: toda rota nova em `routes/api.php` precisa de uma entrada correspondente aqui. Validar com `npx --yes @redocly/cli@latest lint docs/openapi.yaml` antes de commitar — deve terminar em "Woohoo! Your API description is valid" (warnings de `operationId` ausente são aceitáveis, erros não).
- **docs/architecture-walkthrough.md**: só precisa mudar se o fluxo ponta-a-ponta do sistema mudar de forma material (não para toda feature nova).

## Convenções gerais já estabelecidas neste projeto

- Autenticação: Sanctum por token (não cookie-SPA), vocabulário fixo de abilities em `App\Modules\Auth\Domain\ValueObjects\TokenAbility` — todo token emitido no login/registro recebe todas as abilities (`TokenAbility::all()`); não existe ainda um papel de admin separado.
- Money: sempre `App\Shared\Domain\ValueObjects\Money` (wrapper de `brick/money`), moeda única via `config/money.php`.
- Todo domain event só é despachado **depois do commit** da transação que o originou — nunca antes.
- Toda integração com um serviço externo (RabbitMQ, e-mail) passa por um caminho que nunca derruba a resposta HTTP em caso de falha (`SafeIntegrationEventPublisher`, `Mail::queue()` via Horizon) — falha vira log/registro para replay, não exceção propagada.
- Zsh, não bash, é o shell — variável não-quotada não faz word-split; usar palavras literais em loops `for` em vez de iterar sobre uma variável não-quotada.
