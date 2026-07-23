# ADR-0004: Autenticação por token Sanctum (não cookie-SPA)

## Status

Aceito

## Contexto

Laravel Sanctum suporta dois modos de uso bem diferentes: autenticação **stateful via cookie** para SPAs servidas pelo mesmo domínio (ou subdomínio) do backend, e autenticação **stateless via token** (`Bearer` header), pensada para consumidores de API que não compartilham sessão de navegador com o backend.

O frontend deste projeto (React) é um **repositório separado**, potencialmente servido de uma origem diferente (outro domínio, outra porta, ou até um app mobile no futuro). O modo cookie-SPA do Sanctum depende de `EnsureFrontendRequestsAreStateful`, CSRF cookie, e configuração de domínios stateful (`SANCTUM_STATEFUL_DOMAINS`) — mecanismos pensados para quando frontend e backend são percebidos como "a mesma aplicação" pelo navegador. Isso não é o caso aqui.

## Decisão

Autenticação via **token Bearer do Sanctum**, não via cookie/sessão:

- `POST /api/register` e `POST /api/login` retornam `{ user, token }`; o cliente guarda o `token` e o envia em `Authorization: Bearer {token}` em toda request autenticada.
- Rotas autenticadas usam o guard `auth:sanctum`, que resolve o usuário a partir do token, não de sessão/cookie.
- **Escopos de habilidade (abilities) do token são decididos nesta fase**, não deixados para depois: `bid:place`, `profile:read`, `profile:write`, `auction:manage`, `notifications:read` (`Modules\Auth\Domain\ValueObjects\TokenAbility`). O login/registro emite um token com todas as abilities (`TokenAbility::all()`) — decidir o vocabulário agora evita ter que reemitir tokens de todos os usuários quando, em fase futura, for necessário emitir um token mais restrito (ex.: uma integração que só pode dar lance).
- Middlewares `abilities`/`ability` (`Laravel\Sanctum\Http\Middleware\CheckAbilities`/`CheckForAnyAbility`) são registrados manualmente em `bootstrap/app.php`, já que o Laravel 11+ não os registra por padrão como no antigo `Kernel::$routeMiddleware`.
- `config/sanctum.php` e `SANCTUM_STATEFUL_DOMAINS` não são configurados — deliberadamente, para não deixar aberta uma via de autenticação por cookie que não faz sentido para este consumidor.

## Nota: novos contratos no shared kernel

Implementar `Modules\Auth` revelou uma lacuna no shared kernel da Fase 1 (ADR-0003): `Modules\Auth` precisa criar contas e verificar credenciais, mas essas operações vivem em `Modules\User`. Seguindo o padrão já estabelecido ("contrato em `Shared\Domain`, implementação no módulo dono"), três novos contratos foram adicionados: `UserRegistrar` (criar conta), `UserAuthenticator` (verificar credenciais) e `TokenIssuer` (emitir um token Sanctum a partir de um id de usuário). Todos retornam apenas primitivos/arrays associativos — nunca `UserProfile` ou o model Eloquent — para não vazar tipos internos de `Modules\User` através da fronteira. Implementados por `EloquentUserRepository` e `SanctumTokenIssuer`, vinculados em `UserServiceProvider`.

## Consequências

**Positivas**

- Nenhuma dependência de CORS-com-credenciais, CSRF cookie, ou domínios compartilhados entre frontend e backend — o token Bearer funciona de qualquer origem.
- O vocabulário de abilities já existe desde a Fase 2, então a Fase 4 (endpoint de lance) e futuras integrações só precisam referenciar `TokenAbility::BID_PLACE`, não inventar um novo mecanismo de escopo.
- Testes de feature autenticam via `Laravel\Sanctum\Sanctum::actingAs($user, ['*'])` — helper oficial do pacote, sem precisar simular o cookie flow.

**Negativas / trade-offs aceitos**

- Logout revoga apenas o token atual (`currentAccessToken()->delete()`), não todos os tokens do usuário — se o requisito for "sair de todos os dispositivos", precisaria de um endpoint adicional (`$user->tokens()->delete()`). Fora de escopo por ora.
- Diferente do cookie-SPA, o cliente é responsável por armazenar o token com segurança (não fica em cookie `httpOnly` gerenciado pelo navegador) — decisão consciente, delegada ao repositório do frontend.
