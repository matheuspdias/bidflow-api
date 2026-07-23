# ADR-0006: Lock pessimista para concorrência de lances

## Status

Aceito

## Contexto

Vários usuários podem tentar dar lance no mesmo leilão em um intervalo de milissegundos. Sem controle de concorrência, o clássico bug de **lost update** acontece: duas transações leem `current_value = 100` ao mesmo tempo, ambas concluem "meu lance de 150 é válido", ambas escrevem — uma escrita vence silenciosamente, mas **duas linhas de lance aceito** podem ter sido gravadas, ou o `current_value` final pode não refletir o lance correto.

Duas estratégias clássicas resolvem isso: **lock otimista** (versão/timestamp na linha, retry em conflito) ou **lock pessimista** (`SELECT ... FOR UPDATE`, serializando concorrentes na própria transação de banco).

## Decisão

**Lock pessimista.** `AuctionRepository::findByIdForUpdate()` executa `SELECT ... FOR UPDATE` dentro da transação do `PlaceBidUseCase`. Lances concorrentes no **mesmo leilão** naturalmente serializam — a segunda transação só consegue ler a linha depois que a primeira commita, então ela já vê o `current_value` atualizado.

O lock otimista só compensaria em um cenário de alto volume de escrita **cross-shard** ou quando conflitos são raros e o custo de reter um lock é alto — nenhum dos dois é o caso aqui: lances no mesmo leilão são exatamente o cenário que queremos serializar (é a regra de negócio, não um efeito colateral indesejado), e o lock é retido por uma transação curta (algumas queries, não uma chamada de rede externa).

`DB::transaction($callback, attempts: 3)` usa o parâmetro nativo de retry do Laravel para deadlock — não uma estratégia própria. Deadlock aqui só ocorreria por ordem de lock inconsistente entre múltiplas tabelas na mesma transação, algo que o `PlaceBidUseCase` evita ao sempre lockar `auctions` primeiro.

**Toda rejeição também produz uma linha de auditoria, na mesma transação.** Isso exigiu um cuidado específico: se a exceção de domínio (`AuctionClosedException`, `BidTooLowException`, etc.) fosse deixada propagar para fora do `DB::transaction()`, o Laravel faria rollback de **toda* a transação — apagando a própria linha de auditoria que acabamos de gravar! A solução: capturar a exceção *dentro* do closure, gravar a auditoria, retornar `null` (deixando a transação **commitar normalmente**), e só relançar a exceção **depois** que `DB::transaction()` retorna. Nenhuma rejeição fica sem rastro.

**Domain events só são disparados depois do commit.** `PlaceBidUseCase` chama `event($domainEvent)` para cada evento de `$auction->pullDomainEvents()` **fora** do closure da transação, nunca dentro. Disparar antes do commit arriscaria um listener (a partir da Fase 5) agir sobre um lance que um rollback ainda pode desfazer.

## Consequências

**Positivas**

- O teste de concorrência (`tests/Concurrency/BidConcurrencyTest.php`) prova a garantia de verdade: N processos de SO genuinamente concorrentes (via `pcntl_fork`, não threads simuladas dentro de um único processo PHP) disputando o mesmo valor de lance — exatamente um é aceito, os demais rejeitados, sem lance perdido. Validado também ao contrário: com o lock temporariamente desabilitado, o mesmo teste falha 100% das vezes — prova de que o teste é genuíno, não um "sempre verde" por acidente.
- Toda tentativa rejeitada (incluindo por race genuína) aparece em `bid_audit_logs` — nada desaparece silenciosamente.

**Negativas / trade-offs aceitos**

- `SELECT ... FOR UPDATE` retém o lock pelo tempo da transação inteira (checagens + grava lance + atualiza leilão) — mais caro por lance do que uma escrita otimista sem conflito, mas aceitável no volume esperado de um leilão (segundos entre lances, não milhares por segundo).
- O teste de concorrência não pode usar `RefreshDatabase` (cada processo forkado precisa enxergar dados já **commitados** por outra sessão — uma transação de teste não commitada é invisível para outra conexão). Por isso vive em `tests/Concurrency`, uma suíte própria que confirma e limpa seus próprios dados manualmente, em vez de `tests/Feature`.
