# ADR-0005: Ciclo de vida do leilão (state machine)

## Status

Aceito

## Contexto

O aggregate `Auction` precisa de um conjunto pequeno e explícito de estados, com transições válidas bem definidas — é a base sobre a qual a Fase 4 (lances), Fase 10 (anti-sniping) e Fase 11 (encerramento) vão construir. Definir isso de forma frouxa agora ("um leilão tem um `status` string qualquer") geraria estados inconsistentes mais adiante.

## Decisão

Quatro estados, modelados como enum (`App\Modules\Auction\Domain\ValueObjects\AuctionStatus`):

```
SCHEDULED ──activate()──▶ ACTIVE ──(Fase 11: fecha automaticamente)──▶ CLOSED
    │                        │
    └──────cancel()──────────┘
                │
                ▼
           CANCELLED
```

- **`SCHEDULED`**: estado inicial, criado por `Auction::schedule()`. Só neste estado o leilão pode ser editado (`updateDetails()`) — depois de `activate()`, nome/descrição/categoria/janela de datas ficam congelados. Preço inicial, incremento mínimo, `buy_now_price` e `reserve_price` são `readonly` no aggregate desde a criação — mudá-los depois de publicado o leilão mudaria expectativa de quem já está de olho nele, então nem no estado `SCHEDULED` eles são editáveis via `updateDetails()`.
- **`ACTIVE`**: aceita lances (a partir da Fase 4). Só alcançável a partir de `SCHEDULED`.
- **`CANCELLED`**: terminal, alcançável a partir de `SCHEDULED` ou `ACTIVE` via `cancel()`. Não existe um "delete" de leilão — uma vez criado, ele é cancelado, nunca apagado, preservando histórico e integridade referencial com lances/imagens.
- **`CLOSED`**: terminal, alcançado automaticamente pelo scheduler de encerramento (Fase 11), não por uma ação explícita do usuário.

Qualquer transição fora dessas setas lança `InvalidAuctionStatusTransitionException` — inclusive tentar `activate()` duas vezes ou `cancel()` um leilão já `CANCELLED`/`CLOSED`.

`Auction::placeBid()` já existe como assinatura (aceita `bidderId` e `amount`), mas lança `LogicException` incondicionalmente — a lógica real (validar `ACTIVE`, valor mínimo, atualizar `current_value`, registrar `BidPlaced`) é construída na Fase 4. As classes de exceção que essa lógica vai usar (`AuctionClosedException`, `BidTooLowException`) já existem desde já, por serem conceitos de domínio, não detalhe de infraestrutura.

`Auction::isOwnedBy(UserIdentity)` é o único ponto de checagem de propriedade — usado tanto para autorizar edição/ativação/cancelamento (Fase 3) quanto, na Fase 4, para impedir que o vendedor dê lance no próprio leilão.

## Consequências

**Positivas**

- Testar a state machine isoladamente (`tests/Unit/.../AuctionTest.php`) não exige HTTP, banco ou container — só instanciar o aggregate e chamar métodos.
- Rejeição de transição inválida é uma exceção de domínio nomeada, não um `false` silencioso ou um erro genérico — o teste de arquitetura já garante que `Modules\Auction\Domain` não usa `Exception`/`RuntimeException` genéricas para isso.
- `CLOSED` só é alcançável pelo scheduler (não por uma rota HTTP) deixa claro, desde já, que a Fase 11 não vai precisar reabrir essa decisão de design — só implementar o job que chama um método já prometido pela state machine.

**Negativas / trade-offs aceitos**

- Sem rota `DELETE /auctions/{id}` — a "exclusão" de um leilão é sempre `cancel()`. Aceito deliberadamente: um leilão nunca deveria desaparecer sem deixar rastro, mesmo antes de receber lances.
- `auction_images` (migration + model Eloquent) existe desde a Fase 3, mas o endpoint de upload de imagens não foi implementado nesta fase — fora do escopo mínimo do CRUD core; a estrutura já está pronta para quando for priorizado.
