# ADR-0017: Reconexão via gap-fill por id de lance

## Status

Aceito

## Contexto

Uma conexão WebSocket cai — rede instável, aba em segundo plano suspensa pelo navegador, o próprio Reverb reiniciando. O cliente reconecta e se re-inscreve no canal de presence, mas perdeu tudo que aconteceu entre a queda e a reconexão: lances, talvez uma extensão por anti-sniping, talvez o próprio encerramento. `GET /api/auctions/{id}/live` (Fase 9) já existe para dar o estado atual, mas seu `recent_bids` é limitado às últimas 50 entradas — se a queda durou o suficiente para passar disso, ou se o cliente já tinha visto parte dessas 50, a lista certa a devolver não é "as últimas N", é "tudo que aconteceu depois do último lance que eu vi".

## Decisão

`GET /api/auctions/{id}/live?after_bid_id={id}` — o mesmo endpoint, um parâmetro novo, opcional. Quando presente, `recent_bids` deixa de vir da lista do Redis (capada, mais recente primeiro) e passa a vir direto da tabela `bids` via `BidRepository::afterId()`: tudo com `id > after_bid_id`, ordem cronológica (mais antigo primeiro), limitado a 200 entradas — um teto generoso, mas ainda um teto, porque `id` de lance já é exatamente o número de sequência monotonicamente crescente que esse tipo de gap-fill precisa (nenhum novo conceito, nenhuma coluna nova).

Não criou um endpoint novo de propósito: é a mesma chamada que uma tela já faz ao carregar, com o mesmo formato de resposta — o cliente não precisa de um caminho de código diferente para "primeira carga" vs. "reconectei", só passar o parâmetro quando souber o último id visto.

**Achado no caminho, não hipotético**: escrever os testes deste parâmetro expôs que `Cache::flush()` (já usado entre testes, `tests/Pest.php`) isola o facade `Cache`, mas nada isolava chamadas diretas ao facade `Redis` (sets de presence, listas de lances recentes) — testes e o ambiente de desenvolvimento apontavam para o mesmo Redis real, mesmo banco lógico (`REDIS_DB=0`), diferente do Postgres, que já tinha `bidflow_testing` como banco separado desde a Fase 0. Uma chave dinâmica (`auction:{id}:viewers`, onde `id` vem de auto-incremento) pôde colidir com sobra de uma sessão de smoke test manual anterior — exatamente o que aconteceu ao escrever o teste deste parâmetro. Corrigido dando à suíte de testes seu próprio banco lógico Redis (`REDIS_DB=1` em `phpunit.xml`), o mesmo padrão de isolamento que o Postgres já tinha.

## Consequências

**Positivas**

- Nenhum conceito novo — reaproveita `id` do lance (já um inteiro monotonicamente crescente), o endpoint existente, e `BidRepository`.
- Provado com dados reais via HTTP: um leilão com três lances, pedindo tudo após o primeiro, devolve exatamente os outros dois, na ordem certa.
- O isolamento de Redis nos testes (`REDIS_DB=1`) elimina uma classe inteira de flakiness por colisão de id com sobra de sessões manuais — e incidentalmente remove um risco mais sério: sem essa separação, qualquer teste futuro que chamasse algo como `Redis::flushdb()` apagaria dados reais do Horizon/cache de desenvolvimento, não só dados de teste.

**Negativas / trade-offs aceitos**

- Limite de 200 é arbitrário — generoso o bastante para qualquer queda razoável, mas ainda uma escolha sem uma regra de negócio por trás; um cliente que ficou offline tempo suficiente para perder mais que isso simplesmente não recupera tudo (aceitável: nesse cenário, um recarregamento completo da tela é a resposta certa de qualquer forma).
