# Decisões técnicas — FlowCRM

Registro das decisões de arquitetura tomadas durante o desenvolvimento. Cada entrada descreve o contexto, a decisão e o motivo. Decisões ainda em aberto ficam marcadas como **pendente**.

## Organização do repositório

**Decisão:** monorepo único, com `backend/` (Laravel API) e `frontend/` (React SPA) versionados no mesmo repositório Git.
**Motivo:** navegação e avaliação mais simples para portfólio; deploy continua independente entre os dois (EC2/API e S3+CloudFront/SPA).

## Autenticação

**Status: pendente de decisão — a ser definida na Fase 1.**

Laravel Sanctum será utilizado como mecanismo de autenticação da API. A forma exata de uso — cookies de sessão via Sanctum SPA authentication vs. Personal Access Tokens (Bearer) — será comparada objetivamente no início da Fase 1, considerando o cenário de frontend e backend em domínios/subdomínios do mesmo domínio raiz (`app.flowcrm.com` / `api.flowcrm.com`). A decisão final e sua justificativa serão registradas aqui antes da implementação.

Nota de setup: o skeleton padrão do Laravel 13 **não** inclui `laravel/sanctum` nem `routes/api.php` por padrão. Ambos precisam ser adicionados via `php artisan install:api` (ou instalação manual do pacote) — isso será feito na Fase 1, junto da decisão acima.

## Separação Lead vs. Opportunity

**Decisão:** `Lead` e `Opportunity` representam conceitos e pipelines distintos.
- `LeadStatus`: NEW, CONTACTED, QUALIFIED, UNQUALIFIED, CONVERTED
- `OpportunityStage`: NEW, CONTACTED, PROPOSAL, NEGOTIATION, WON, LOST

**Motivo:** um Lead representa alguém em processo de qualificação; uma Opportunity representa uma negociação comercial em andamento. Tratá-los como o mesmo pipeline misturaria estágios de qualificação com estágios de venda.

A conversão de um Lead qualificado (`Lead` → `Client` + `Opportunity`) será implementada como uma operação de negócio transacional na fase correspondente.

## Valores monetários

**Decisão:** todos os campos monetários (`estimated_value`, `value`, `price`, `unit_price`, `subtotal`, `discount`, `total`) usarão `DECIMAL(15,2)`, nunca `FLOAT`/`DOUBLE`.
**Motivo:** evitar erros de arredondamento em cálculos financeiros.

## Chaves primárias

**Status: pendente de decisão — a ser avaliada antes das migrations (Fase 1/2).**

Uso de ULID como chave primária das entidades principais será avaliado frente a IDs incrementais, considerando suporte do Eloquent, performance de FK no MySQL, legibilidade e exposição/enumeração pública. Decisão e justificativa serão registradas aqui antes da criação das migrations.

## Multi-tenancy

**Decisão:** estratégia *Shared Database + Shared Schema*, com coluna `company_id` nas tabelas de domínio comercial (`leads`, `clients`, `opportunities`, `proposals`, `tasks`, etc.).
**Motivo:** simplicidade operacional adequada ao estágio do produto, evitando a complexidade de bancos/schemas por tenant.

**Cuidado already acordado:** o isolamento de tenant não pode depender de Global Scopes acoplados diretamente a `auth()`, pois isso quebra em contextos sem request HTTP (queues, jobs, commands, seeders, testes). A solução para o trait `BelongsToCompany` (ou equivalente) será proposta e revisada antes da implementação, na fase de Auth + Multi-tenancy.

Defesa em profundidade combinando: tenant scope + Policies + validação + testes de isolamento explícitos. O `company_id` nunca é aceito vindo do frontend — é sempre derivado do usuário autenticado/contexto confiável do backend.

## API

**Decisão:** API REST versionada desde o início, sob o prefixo `/api/v1`.
**Motivo:** permitir evolução da API sem quebrar consumidores existentes.

## Banco de dados

**Decisão:** MySQL como banco de dados relacional (local e RDS MySQL em produção).
**Status:** nenhuma migration criada ainda. O modelo de dados (ERD) será apresentado e aprovado antes da criação de qualquer migration.
