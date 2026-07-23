# ADR-0007: Estratégia de idempotency key para lances

## Status

Aceito

## Contexto

Uma requisição de lance pode falhar por timeout de rede **depois** de ter sido processada com sucesso no servidor — o cliente não sabe se o lance foi aceito e, sem alguma forma de idempotência, a reação natural (tentar de novo) poderia gerar um segundo lance indevido.

## Decisão

Endpoint `POST /api/auctions/{id}/bids` exige o header `Idempotency-Key` (qualquer string única gerada pelo cliente — um UUID é o padrão recomendado). Implementado como middleware (`EnsureIdempotentBidRequest`), não dentro do `PlaceBidUseCase`: idempotência de request HTTP é uma preocupação de transporte, não uma regra de negócio do domínio de leilão.

Fluxo do middleware:
1. Sem o header → `400 Bad Request` (falha rápida, explícita).
2. Header presente, já visto para este `(bidder_id, idempotency_key)` → devolve a resposta cacheada (`bid_idempotency_keys.response_status` + `response_body`) sem tocar no `PlaceBidUseCase` de novo.
3. Header novo → deixa a requisição seguir, e grava a resposta (sucesso **ou** erro) depois que o controller responde.

A chave é escopada por **bidder**, não global — `unique(bidder_id, idempotency_key)` no banco. Duas pessoas diferentes usando a mesma string de chave (coincidência ou não) são tratadas como requisições independentes; só faz sentido colidir dentro do histórico do mesmo usuário.

## Consequências

**Positivas**

- Retry de rede do cliente é seguro por padrão — nenhuma lógica adicional no frontend além de reenviar a mesma chave.
- Cachear a resposta inteira (status + corpo), não só "já processado: sim/não", significa que o replay devolve exatamente o que o cliente teria recebido da primeira vez — inclusive em caminhos de erro (ex.: replay de uma tentativa que foi rejeitada por lance baixo continua devolvendo 422 com a mesma mensagem, não um 200 incoerente).

**Negativas / trade-offs aceitos**

- É um *check-then-act*: duas requisições com a **mesma chave** chegando genuinamente ao mesmo tempo (não um retry sequencial, mas duas de verdade simultâneas) poderiam ambas passar pela checagem "ainda não visto" antes de qualquer uma gravar. Aceito deliberadamente — esse cenário é distinto da concorrência que a ADR-0006 resolve (lances de bidders *diferentes* no mesmo leilão): um cliente não emite duas requisições simultâneas com a mesma chave em uso normal, só em retry após timeout, que por definição não é simultâneo com a tentativa original.
- Nenhuma expiração/limpeza de `bid_idempotency_keys` foi implementada — a tabela cresce indefinidamente. Aceitável na escala de um projeto de portfólio; um sistema de produção adicionaria um job de limpeza por idade.
