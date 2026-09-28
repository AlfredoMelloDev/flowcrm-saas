# FlowCRM

FlowCRM é um CRM SaaS full-stack multi-tenant desenvolvido para gerenciamento do processo comercial de pequenas e médias empresas.

O projeto foi criado também como projeto de portfólio, com foco em arquitetura, segurança, isolamento de dados entre empresas, autorização baseada em papéis, qualidade de código e testes automatizados.

## Tecnologias

**Backend**
- PHP
- Laravel
- Laravel Sanctum
- Eloquent ORM
- MySQL
- API REST

**Frontend**
- React
- Vite
- React Router
- Axios
- TanStack Query
- Tailwind CSS v4

**Qualidade**
- PHPUnit / testes Laravel
- Testes frontend (Vitest + Testing Library)
- Laravel Pint
- Git
- Conventional Commits

## Funcionalidades implementadas

### Autenticação

- Registro de empresa e usuário administrador
- Login e logout
- Sessão autenticada com Laravel Sanctum (cookie SPA, não token)
- Endpoint de usuário autenticado
- Validação de conta ativa (usuário e empresa)

### Multi-tenancy

O sistema usa um único banco de dados compartilhado entre todas as empresas (*shared database, shared schema*), com isolamento lógico por `company_id` em cada tabela de domínio comercial.

Um `TenantContext` estabelece qual empresa está ativa na requisição atual, e um `TenantScope` global aplica esse filtro automaticamente em toda consulta Eloquent dos modelos tenant-aware. A atribuição do `company_id` em criações é sempre feita pelo backend — nunca aceita vinda do frontend — o que evita que um usuário acesse ou manipule dados de outra empresa.

### Controle de acesso

Três papéis de usuário:

- **ADMIN**
- **MANAGER**
- **SELLER**

Policies e Gates do próprio Laravel controlam o que cada papel pode ver e fazer. De forma geral, ADMIN e MANAGER têm visibilidade total dos registros da empresa, enquanto SELLER tem acesso restrito aos registros atribuídos a ele quando aplicável.

### Leads

- CRUD completo
- Atribuição de responsável
- Status
- Origem
- Valor potencial
- Busca
- Filtros
- Ordenação
- Paginação
- Isolamento por empresa
- Autorização por papel

### Clientes

- CRUD completo
- Cliente pessoa física ou empresa
- Responsável
- Status
- Documento
- Busca
- Filtros
- Ordenação
- Paginação
- Soft delete
- Unicidade de documento por empresa
- Isolamento multi-tenant

## Em desenvolvimento

### Opportunities / Pipeline

A próxima etapa adiciona o gerenciamento de oportunidades comerciais e um pipeline visual, com estágios NEW, CONTACTED, PROPOSAL, NEGOTIATION, WON e LOST, associação com clientes, valores e previsão de fechamento.

🚧 Esta funcionalidade está em desenvolvimento e ainda não faz parte da versão estável do projeto.

## Arquitetura

Estrutura simplificada do repositório:

```
flowcrm/
├── backend/     # API Laravel
├── frontend/    # SPA React (Vite)
├── docs/        # Documentação de arquitetura e decisões técnicas
└── README.md
```

Fluxo de requisições:

```
React SPA
    ↓
Laravel REST API
    ↓
MySQL
```

Backend e frontend são desacoplados: o backend expõe uma API REST versionada (`/api/v1`) e o frontend é uma SPA React que consome essa API via Axios, sem servir HTML gerado pelo Laravel.

## Segurança e isolamento

- `company_id` nunca é confiado ao frontend — é sempre derivado da empresa do usuário autenticado
- Escopo de tenant aplicado automaticamente no backend em todas as consultas de modelos tenant-aware
- Route model binding executado somente após o contexto de tenant ser estabelecido na requisição
- Autorização de ações via Policies/Gates do Laravel
- Validação de atribuições (responsável) restrita a usuários da mesma empresa
- Autenticação via Laravel Sanctum (cookie de sessão, não token exposto ao JavaScript)

Essas camadas reduzem riscos conhecidos de vazamento de dados entre empresas, mas nenhum sistema é absolutamente livre de falhas — por isso a cobertura de testes de isolamento é tratada como parte central do desenvolvimento.

## Testes

Números da última fase estável publicada (Autenticação, Multi-tenancy, Leads e Clientes):

**Backend:** 130 testes, 360 assertions
**Frontend:** 26 testes

Os testes cobrem autenticação, multi-tenancy, autorização por papel, isolamento de dados entre empresas, e as regras de negócio de Leads e Clientes.

## IA no desenvolvimento

Ferramentas de IA são utilizadas como apoio durante o desenvolvimento deste projeto, para:

- Revisão de arquitetura
- Auxílio na implementação
- Geração e expansão de casos de teste
- Análise de falhas
- Refatoração

Decisões arquiteturais, revisão das alterações propostas, execução dos testes e validação final continuam sendo parte do processo de desenvolvimento — a IA acelera a implementação, mas não substitui essa revisão.

**Exemplo real:** durante a implementação de Leads, os testes revelaram um problema na ordem dos middlewares — o route model binding podia acontecer antes da definição do `TenantContext` da requisição. A prioridade dos middlewares foi corrigida para garantir que o contexto da empresa seja estabelecido antes do `SubstituteBindings`. Esse caso ilustra como testes e revisão detectaram um problema real de isolamento durante o desenvolvimento, antes de chegar a produção.

## Roadmap

- [x] Estrutura inicial
- [x] Autenticação e Multi-tenancy
- [x] Leads
- [x] Clients
- [ ] Opportunities / Pipeline
- [ ] Conversão Lead → Client + Opportunity
- [ ] Propostas
- [ ] Produtos e Serviços
- [ ] Tarefas e Atividades
- [ ] Anexos
- [ ] Dashboard e Relatórios
- [ ] Docker / Docker Compose
- [ ] Deploy AWS (EC2, RDS, S3, CloudWatch)

## Como executar

Pré-requisitos: PHP 8.3+, Composer, Node.js, npm e uma instância MySQL disponível.

### Backend (API Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Ajuste `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no `.env` conforme sua instância MySQL local (o `.env.example` já vem configurado para `127.0.0.1:3307`, database `flowcrm`).

```bash
php artisan migrate
php artisan serve
```

A API sobe em `http://localhost:8000`.

### Frontend (SPA React)

Em outro terminal:

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

A SPA sobe em `http://localhost:5173` e consome a API em `VITE_API_URL` (padrão `http://localhost:8000`, definido em `frontend/.env`).

### Rodando os testes

```bash
# Backend
cd backend
composer test    # ou: php artisan test
./vendor/bin/pint

# Frontend
cd frontend
npm run test
npm run build
```

## Status

🚧 Em desenvolvimento ativo.

Fases 0–3 concluídas (estrutura inicial, autenticação, multi-tenancy, Leads e Clientes).
Fase 4 — Opportunities/Pipeline em desenvolvimento.

## Autor

**Alfredo Mello**
GitHub: [AlfredoMelloDev](https://github.com/AlfredoMelloDev)
