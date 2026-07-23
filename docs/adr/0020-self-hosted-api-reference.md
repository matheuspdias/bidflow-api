# ADR-0020: Documentação da API self-hosted (Redoc, sem CDN)

## Status

Aceito

## Contexto

`docs/openapi.yaml` (Fase 16) é a fonte de verdade da API REST, mas é um arquivo estático — só útil de verdade para quem já sabe procurar por ele no repositório e tem alguma ferramenta para renderizá-lo. Faltava uma forma de olhar a documentação como página, sem depender de nada externo ao próprio `docker-compose` — o mesmo motivo que levou o projeto a escolher Reverb em vez de Pusher/Ably (ADR-0011): um projeto de portfólio existe para *mostrar* como o sistema se encaixa, e depender de um serviço de terceiro (um Swagger Hub, um Redocly hospedado) esconderia exatamente essa parte.

## Decisão

**Redoc**, não Swagger UI: o spec é uma referência para ler, não uma superfície de teste — Redoc é uma página estática de três colunas (like os docs da Stripe), sem o "Try it out" que o Swagger UI adiciona (que exigiria lidar com CORS e coleta de token Bearer só para uma feature que este projeto não precisa; testar a API de verdade já tem os testes automatizados e a suíte de smoke tests manuais documentada em `CLAUDE.md`).

**Bundle vendorizado, não CDN**: `redoc.standalone.js` (v2.5.3, MIT) baixado uma vez e commitado em `public/vendor/redoc/` — servido como asset estático pelo próprio nginx do `docker-compose`, sem chamada de rede em tempo de execução. `GET /docs` devolve uma página HTML mínima (`resources/docs/index.html`) que inicializa o Redoc apontando para `GET /docs/openapi.yaml`.

**Uma fonte de verdade, sem cópia**: `/docs/openapi.yaml` não é um arquivo duplicado em `public/` — é uma rota (`routes/web.php`) que serve `docs/openapi.yaml` diretamente do disco (`response()->file(base_path('docs/openapi.yaml'))`). Editar o YAML não exige nenhum passo de build ou sincronização; a página sempre reflete o arquivo atual.

As duas rotas ficam em `routes/web.php`, não dentro de nenhum módulo de domínio (`Auction`/`User`/etc.) — documentação da API é uma preocupação transversal de apresentação, não uma regra de negócio de módulo nenhum, o mesmo raciocínio que já colocava a rota `/` de health-check ali.

## Consequências

**Positivas**

- Nenhuma dependência de rede em tempo de execução — `docker compose up` sozinho é suficiente, mesmo sem acesso à internet depois do build inicial.
- Zero duplicação: `docs/openapi.yaml` continua sendo o único arquivo que qualquer um precisa editar.
- Provado com os três caminhos reais: `GET /docs` (200, referencia o bundle vendorizado e a rota do spec), `GET /docs/openapi.yaml` (200, `Content-Type: application/yaml`, bytes idênticos ao arquivo em disco), `GET /vendor/redoc/redoc.standalone.js` (200, servido direto pelo nginx).

**Negativas / trade-offs aceitos**

- ~1MB de bundle JS commitado no repositório — aceitável para um projeto deste tamanho; um projeto com política estrita de tamanho de repo preferiria buscar isso num passo de build em vez de commitar o binário.
- Sem verificação visual automatizada (nenhum browser headless disponível neste ambiente para captura de tela) — validado via HTTP (status, `Content-Type`, conteúdo exato) e por leitura do HTML gerado, não por renderização real. Redoc é uma biblioteca madura e amplamente usada com uma API de inicialização simples (`Redoc.init(specUrl, options, container)`), então o risco residual é baixo, mas não é o mesmo nível de prova que os smoke tests com WebSocket real feitos nas fases anteriores.
