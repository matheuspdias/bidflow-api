# ADR-0001: Monólito modular com vertical slices

## Status

Aceito

## Contexto

O BidFlow API precisa demonstrar, dentro de um projeto de portfólio de escopo razoável, como organizar um sistema com múltiplos domínios de negócio (leilões, usuários, notificações, dashboards, autenticação) sem cair em duas armadilhas comuns:

1. Um monólito "big ball of mud", onde qualquer classe pode depender de qualquer outra e a fronteira entre domínios existe apenas na cabeça de quem escreveu o código.
2. Microsserviços prematuros, que resolveriam um problema de escala que este projeto não tem, ao custo de complexidade operacional (deploy, observabilidade distribuída, transações distribuídas) desproporcional ao benefício demonstrado.

## Decisão

Adotar um **monólito modular organizado em vertical slices por domínio de negócio**: `Auction`, `User`, `Notification`, `Dashboard`, `Auth`. Cada módulo vive em `src/Modules/{Módulo}` e contém sua própria fatia completa — domínio, aplicação, infraestrutura e apresentação (ver ADR-0002) — em vez de organizar o código por camada técnica horizontal (todos os controllers juntos, todos os models juntos, etc.).

Comunicação entre módulos acontece **apenas através de contratos publicados em `Shared\Domain`** (ex.: `Shared\Domain\Contracts\SellerLookup`), nunca importando classes internas de outro módulo diretamente. Essa regra é verificada automaticamente por um teste de arquitetura (`tests/Architecture/BoundariesTest.php`, usando `pestphp/pest-plugin-arch`) que roda no CI a cada fase.

`Bid` é uma exceção deliberada: mora dentro de `Modules/Auction/Domain` como entidade filha do aggregate `Auction`, não como módulo próprio — um lance não tem ciclo de vida independente de um leilão, então não faz sentido tratá-lo como uma fatia vertical separada.

## Consequências

**Positivas**

- Cada módulo é, na prática, um candidato a serviço extraível — se um dia a escala justificasse separar `Notification` em um serviço próprio, a fronteira de contrato já existe e o corte é mecânico.
- A regra de fronteira testada automaticamente impede que acoplamento oculto se acumule silenciosamente conforme o projeto cresce fase a fase.
- Deploy continua simples (um único artefato), o que é apropriado para o estágio e o propósito (portfólio) deste projeto.

**Negativas / trade-offs aceitos**

- Alguma duplicação é aceitável e esperada entre módulos (ex.: cada módulo pode ter sua própria noção de "usuário" via um contrato local) em troca de baixo acoplamento.
- Exige disciplina: é sempre tecnicamente possível quebrar a regra de fronteira sem o teste de arquitetura pego a tempo — por isso o teste existe desde a Fase 0, não é adicionado depois como reforço.
