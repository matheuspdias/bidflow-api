# ADR-0003: Padrão de contrato do shared kernel

## Status

Aceito

## Contexto

A ADR-0001 estabeleceu que módulos só podem se comunicar através de contratos publicados em `Shared\Domain`. A Fase 1 é onde esse *shared kernel* ganha conteúdo real pela primeira vez, então é o momento de fixar o padrão que os contratos seguem — antes que o primeiro módulo (Fase 2) precise implementá-los.

Três categorias de conteúdo compõem o shared kernel:

1. **Value Objects** (`Shared\Domain\ValueObjects`): `Money`, `DateRange`, `AggregateId` — tipos primitivos de negócio usados por mais de um módulo, para não duplicar lógica de arredondamento monetário ou validação de intervalo de datas em cada módulo separadamente.
2. **Contratos entre módulos** (`Shared\Domain\Contracts`): `UserIdentity`, `SellerLookup`, `BidderLookup` — interfaces que um módulo publica como *o que ele expõe*, e outro módulo consome sem conhecer a implementação.
3. **Contratos de evento** (`Shared\Domain\Events`): `DomainEvent`, `IntegrationEvent` — marcam a distinção entre um evento interno ao processo (ver ADR-0008, Fase 5) e um evento publicado para outros processos.

## Decisão

**Value Objects são imutáveis e envolvem bibliotecas de terceiros em vez de expor a API delas.** `Money`, por exemplo, envolve `brick/money` — o resto do domínio nunca importa `Brick\Money\Money` diretamente, só `App\Shared\Domain\ValueObjects\Money`. Isso significa que uma eventual troca de biblioteca de precisão monetária afeta uma única classe.

**Contratos entre módulos são interfaces pequenas e específicas de propósito, não um "God contract".** Em vez de uma interface genérica `UserRepository` exposta a todos os módulos, existem três interfaces separadas — `UserIdentity` (quem é o usuário atual), `SellerLookup` (fatos sobre um vendedor), `BidderLookup` (fatos sobre um licitante) — cada uma perguntando exatamente o que o módulo consumidor precisa saber, nada mais. Isso é Interface Segregation aplicado deliberadamente na fronteira entre módulos: `Modules\Auction` não deveria conseguir, através do contrato, alcançar funcionalidade de `Modules\User` que não precisa (como editar o perfil do usuário).

**Contratos ficam em `Shared\Domain`, implementações ficam no módulo que os satisfaz.** `Modules\User\Infrastructure` implementa `UserIdentity`, `SellerLookup` e `BidderLookup` (a partir da Fase 2); `Modules\Auction` depende apenas da interface, resolvida via container. O módulo que expõe dados é quem escreve o adapter — não o módulo consumidor.

**`Shared\Domain` não depende de `Illuminate\*`, nem de exceções genéricas (`Exception`, `RuntimeException`) diretamente.** Ambas as regras são verificadas por `tests/Architecture/BoundariesTest.php`. Validação de invariante de Value Object usa exceções específicas do SPL (`InvalidArgumentException`) em vez de `Exception`/`RuntimeException` genéricas — suficiente para VOs; exceções de regra de negócio de aggregate (ex. `AuctionClosedException`) são tratadas à parte, dentro de cada módulo (Fase 3).

**Infraestrutura compartilhada (`Shared\Infrastructure`) pode depender do framework livremente.** `RabbitMqConnectionFactory` e `RabbitMqPublisher` usam o helper `config()` do Laravel normalmente — a restrição de dependência de framework vale só para `Shared\Domain`, não para `Shared\Infrastructure`, seguindo a mesma regra de camadas da ADR-0002.

**CommandBus/QueryBus são implementação própria, não um pacote.** Ambos resolvem o handler através do container do Laravel (`Illuminate\Contracts\Container\Container`), registrado como singleton em `AppServiceProvider` para que o mapeamento comando→handler registrado por cada módulo sobreviva durante toda a request. Um pacote de terceiros para isso seria complexidade desnecessária para um monólito deste porte.

## Consequências

**Positivas**

- Cada contrato tem uma responsabilidade nomeável e testável isoladamente — a Fase 2 pode provar que `UserIdentityAdapter implements UserIdentity` funciona sem esperar por nenhum outro módulo existir.
- `Money` e `DateRange`, sendo puros (zero dependência de framework), são triviais de testar unitariamente sem bootstrap do Laravel — os testes desta fase rodam em milissegundos.
- A regra "contrato em `Shared\Domain`, implementação no módulo dono" é simples de lembrar e de aplicar conforme novos contratos aparecerem em fases futuras.

**Negativas / trade-offs aceitos**

- Três interfaces pequenas (`UserIdentity`, `SellerLookup`, `BidderLookup`) em vez de uma são mais arquivos para navegar — aceito em troca de cada uma ser fácil de entender isoladamente e de não vazar responsabilidade entre módulos.
- `RabbitMqConnectionFactory`/`RabbitMqPublisher` existem desde já mas ficam sem nenhum consumidor até a Fase 5 — código "morto" temporariamente, mas construído agora porque a Fase 5 precisa dele pronto para focar exclusivamente na tradução domain→integration event, não na infraestrutura de conexão.
