# Decisões técnicas — FlowCRM

Registro das decisões de arquitetura tomadas durante o desenvolvimento. Cada entrada descreve o contexto, a decisão e o motivo. Decisões ainda em aberto ficam marcadas como **pendente**.

## Organização do repositório

**Decisão:** monorepo único, com `backend/` (Laravel API) e `frontend/` (React SPA) versionados no mesmo repositório Git.
**Motivo:** navegação e avaliação mais simples para portfólio; deploy continua independente entre os dois (EC2/API e S3+CloudFront/SPA).

## Autenticação

**Decisão:** Laravel Sanctum em modo **SPA authentication (cookie de sessão HttpOnly)**, não Personal Access Tokens (Bearer).

**Motivo:** o React é first-party e compartilhará domínio raiz com a API em produção (`app.flowcrm.com` / `api.flowcrm.com`), exatamente o cenário para o qual o modo SPA do Sanctum existe. O cookie de sessão é HttpOnly — inacessível a JavaScript — eliminando a classe de risco de roubo de token via XSS, que é o vetor mais realista contra uma SPA. CSRF é mitigado automaticamente pelo padrão double-submit cookie (`XSRF-TOKEN`) que o próprio Sanctum implementa. Personal Access Tokens exigiriam armazenar o token em `localStorage`/`sessionStorage`, legível por qualquer script — inclusive uma dependência npm comprometida — o que não se justifica aqui só para simplificar CORS.

Personal Access Tokens continuam disponíveis no Sanctum e podem coexistir no futuro (app mobile, integração de terceiros) sem conflito com o guard `auth:sanctum` — não é uma decisão que trava a arquitetura.

**Implementação:**
- `SANCTUM_STATEFUL_DOMAINS=localhost:5173` no `.env` define que origem do frontend recebe autenticação stateful.
- `SESSION_DOMAIN=null` em desenvolvimento — validado empiricamente (curl + navegador real via fetch cross-porta) que tanto `null` (cookie host-only, sem atributo `Domain`) quanto um valor explícito `localhost` funcionam entre `localhost:5173` e `localhost:8001`, já que cookies não são escopados por porta. Mantido `null` por ser o default de fábrica do Laravel e mais restritivo (não habilita correspondência de subdomínio à toa). Em produção, `SESSION_DOMAIN` deverá ser o domínio raiz com ponto à esquerda (`.flowcrm.com`) — isso sim é necessário, pois `app.flowcrm.com` e `api.flowcrm.com` são subdomínios diferentes e só compartilham cookie com o atributo `Domain` explícito.
- `config/cors.php`: `allowed_origins` explícito (nunca `*`) + `supports_credentials => true`, exigido pelo fluxo de cookies.
- Fluxo do frontend: `GET /sanctum/csrf-cookie` antes do primeiro POST, depois axios com `withCredentials: true`.
- `personal_access_tokens` foi criada automaticamente pelo procedimento oficial de instalação (`composer require laravel/sanctum` + `php artisan install:api`) — não foi adicionada manualmente. Mantida (não removida), pois faz parte do scaffolding padrão do Sanctum e não atrapalha; ajustada apenas para usar `ulidMorphs('tokenable')` em vez do `morphs()` padrão (bigint), compatibilizando com o PK ULID de `User`. **Personal Access Tokens não são utilizados atualmente** — o FlowCRM usa exclusivamente Sanctum SPA (cookie de sessão) neste momento; a tabela existe pronta caso um cliente não-SPA (mobile, integração) precise de tokens no futuro.

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

**Decisão:** ULID (`char(26)`) como chave primária de `companies` e `users`, via trait nativa `Illuminate\Database\Eloquent\Concerns\HasUlids` (sem pacote externo) e `$table->ulid('id')->primary()` / `$table->foreignUlid(...)` nas migrations.

**Motivo:** IDs incrementais expõem contagem/ordem de criação e são enumeráveis (`/leads/1`, `/leads/2`...); ULID é ordenável por tempo de criação (bom para índice InnoDB) mas não sequencial nem previsível. Suporte nativo no Laravel/Eloquent (route model binding, factories) sem custo de complexidade adicional relevante para este projeto.

## Multi-tenancy

**Decisão:** estratégia *Shared Database + Shared Schema*, com coluna `company_id` nas tabelas de domínio comercial (`leads`, `clients`, `opportunities`, `proposals`, `tasks`, etc.).
**Motivo:** simplicidade operacional adequada ao estágio do produto, evitando a complexidade de bancos/schemas por tenant.

**Mecanismo (implementado na Fase 1):** `App\Support\Tenancy\TenantContext` — singleton simples ligado ao container (`$this->app->singleton(TenantContext::class)`), sem Facade. Nunca lido via `auth()` dentro de scopes; é setado explicitamente por quem estabelece o limite de tenant em cada contexto de execução:
- **HTTP:** middleware `App\Http\Middleware\SetTenantContext`, executado após `auth:sanctum` no grupo `api`, faz `$tenantContext->set($user->company_id)`.
- **Queues/commands/seeders/testes:** nada os popula automaticamente — cada um precisa setar o contexto explicitamente antes de tocar um model tenant-scoped (a ser feito conforme esses contextos surgirem, a partir da Fase 2).

`App\Models\Concerns\BelongsToCompany` (trait, ainda sem nenhum model consumidor — o primeiro será `Lead` na Fase 2): aplica `TenantScope` (Global Scope) e, no evento `creating`, atribui `$model->company_id = $tenantContext->id()` de forma **autoritativa** (nunca `??=`) — um `company_id` pré-preenchido no model não sobrevive à criação.

**Fail closed:** `TenantContext::id()` lança `TenantContextMissingException` quando o contexto não foi setado, em vez de devolver todos os registros (vazamento) ou nenhum (mascara o bug). Qualquer rota/comando que esqueça de estabelecer o contexto falha alto, imediatamente.

**`User` é exceção deliberada:** não usa `BelongsToCompany` nem `TenantScope`, porque o login busca o usuário por e-mail *antes* de existir contexto de tenant. Endpoints futuros de administração de usuários deverão escopar por `company_id` explicitamente e usar Policy — nunca Global Scope automático.

Defesa em profundidade combinando: tenant scope + Policies + validação + testes de isolamento explícitos. O `company_id` nunca é aceito vindo do frontend — nunca faz parte de `$fillable`/regras de validação dos FormRequests de recursos tenant-scoped; é sempre derivado do usuário autenticado via `TenantContext`.

## Status de User/Company em requests autenticados

**Decisão:** `UserStatus::Inactive` e `CompanyStatus::Suspended` bloqueiam login e derrubam sessões já existentes — não apenas no momento do login.

**Mecanismo (única fonte de verdade, sem duplicação):** `User::isUsable(): bool` centraliza a regra (`status === Active && company->status === Active`). Dois pontos de aplicação reutilizam o mesmo método:
- `AuthController::login()` — após `Auth::attempt()` validar as credenciais, se `! $user->isUsable()`, a sessão recém-criada é encerrada e a mesma mensagem genérica de credenciais inválidas é retornada (não revela se a conta existe, está inativa, ou pertence a uma empresa suspensa).
- `App\Http\Middleware\EnsureAccountIsActive`, aplicado junto com `auth:sanctum` nas rotas protegidas (`logout`, `me`) — a cada request, se a conta deixou de ser utilizável desde o login, a sessão é encerrada ali mesmo e a resposta é `401` (mesmo formato de "não autenticado"), impedindo que uma sessão existente continue válida indefinidamente após a empresa/usuário serem desativados.

Encerrar a sessão (logout + invalidate + regenerate token) é feito por uma única Action (`App\Actions\Auth\TerminateSession`), reutilizada por `logout()`, `login()` (caminho de rejeição) e `EnsureAccountIsActive` — evita repetir essa lógica em três lugares.

## RBAC (Admin / Manager / Seller)

**Decisão:** recursos nativos do Laravel — enum `App\Enums\UserRole` + Policies/Gates + middleware quando necessário. Sem pacote externo (`spatie/laravel-permission` avaliado e descartado por ora).

**Motivo:** são 3 papéis fixos e fechados, não dinâmicos/customizáveis pelo usuário final — um enum resolve isso sem o overhead de tabelas extras (`roles`, `permissions`, `model_has_roles`) que um pacote de RBAC dinâmico introduziria. Reavaliar apenas se surgir necessidade real de papéis customizáveis por empresa.

## API

**Decisão:** API REST versionada desde o início, sob o prefixo `/api/v1`.
**Motivo:** permitir evolução da API sem quebrar consumidores existentes.

## Banco de dados

**Decisão:** MySQL como banco de dados relacional (local e RDS MySQL em produção).

**Ambiente de desenvolvimento local (Fase 1):** MySQL 8.4 (trilho LTS) em container Docker avulso (`flowcrm-mysql`, porta `3307` do host — `3306` já estava em uso por outro projeto local), com volume nomeado para persistência. Não é a dockerização da aplicação — Laravel e Vite continuam rodando localmente; é só a infraestrutura de banco. Credenciais reais apenas em `backend/.env` (não versionado); `backend/.env.example` traz placeholders seguros.

**Tabelas criadas na Fase 1:** `companies`, `users` (ambas com PK ULID), mais as tabelas padrão do Laravel/Sanctum (`sessions`, `cache`, `jobs`, `password_reset_tokens`, `personal_access_tokens`).
