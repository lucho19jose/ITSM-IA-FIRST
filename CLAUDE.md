# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**AutoService** — Plataforma ITSM AI-first para Perú. Competencia directa de Freshservice/ServiceNow pero con IA nativa, precios en soles (PEN), y cumplimiento regulatorio peruano. Target: empresas medianas (50-250 empleados).

## Environment

- Laragon webroot: `C:\laragon\www`
- PHP 8.2.29, Node.js v22.20.0, MySQL 8.0.30, Composer 2.4.1
- Virtual host: `autoservice.test` → `backend/public`

## Architecture

Monorepo con 2 proyectos independientes:

- **`backend/`** — Laravel 11 API-only (REST). Multi-tenant single DB con `tenant_id` + Global Scope (`BelongsToTenant` trait). Auth via Laravel Passport (OAuth2 tokens). AI processing via queued Jobs (database driver).
- **`frontend/`** — Vue 3 + Quasar 2 + TypeScript SPA. Pinia stores, vue-i18n (español default), Vite con proxy a Laravel. Puerto dev: 5174.

## Commands

### Backend (`cd backend/`)
```bash
php artisan serve                    # Dev server (o usar Laragon vhost)
php artisan migrate                  # Correr migraciones
php artisan migrate:fresh --seed     # Reset DB con datos demo
php artisan db:seed                  # Seeders (categorías, SLA, datos demo)
php artisan test                     # Todos los tests
php artisan test --filter=TicketCrud # Test específico
php artisan queue:work               # Procesar jobs AI (clasificación, etc.)
php artisan schedule:run             # Ejecutar tareas programadas (SLA check)
./vendor/bin/pint                    # Code formatting (Laravel Pint)
```

### Frontend (`cd frontend/`)
```bash
npm run dev                          # Vite dev server (port 5174)
npm run build                        # Build producción
npm run preview                      # Preview build
npm run test                         # Vitest
npm run test:watch                   # Vitest watch mode
npx vue-tsc --noEmit                 # Type check
```

## Key Patterns

- **Multi-tenancy**: Todo modelo tenant-scoped usa el trait `BelongsToTenant` que aplica `TenantScope` automáticamente y setea `tenant_id` al crear. Middleware `SetTenantContext` en rutas autenticadas.
- **AI processing**: Los tickets se clasifican automáticamente via `ClassifyTicketJob` (async). Umbral de auto-aplicación: confianza > 70%. Prompts en `resources/prompts/` como Blade templates.
- **Roles**: `admin` (todo), `agent` (tickets + KB), `end_user` (solo sus tickets + KB público). Implementado con middleware `CheckRole` + Laravel Policies + router guards frontend.
- **API versioning**: Todas las rutas bajo `/api/v1/`. Responses con Laravel Resources.
- **Composition API siempre**: Todos los componentes Vue usan `<script setup lang="ts">`, stores Pinia con `defineStore` composition syntax, nunca Options API.
- **API centralizada**: Axios instance en `src/utils/api.ts` exporta `{ get, post, put, del }`. Módulos por recurso en `src/api/` usan esos helpers. Componentes solo importan funciones del módulo, nunca llaman axios directamente. Ejemplo: `import { getTickets } from '@/api/tickets'`.
- **i18n**: Español como default (`es/`), inglés secundario (`en/`). Traducciones en `src/i18n/`.
- **Path alias**: `@` → `src/` en frontend (tsconfig + vite).

## AI Integration

- Provider: Anthropic Claude API (claude-sonnet-4-20250514)
- Config: `config/ai.php` con API key, modelo, rate limits
- Services en `app/Services/Ai/`: ClaudeClient, TicketClassifier, ResponseSuggester, KbGenerator, ChatbotService, SlaPredictorService
- Cada interacción AI se loguea en tabla `ai_suggestions`

## Peru-Specific

- RUC: campo de 11 dígitos en tenant (validación SUNAT)
- Moneda: PEN (sol peruano), helpers en `src/utils/currency.ts`
- Timezone: `America/Lima` por defecto
- Ley 29733: consentimiento en registro, export/eliminación de datos
- Facturación electrónica: estructura preparada (SUNAT CPE placeholder)

## Database

MySQL `autoservice_db`. Tablas principales: `tenants`, `users`, `tickets`, `ticket_comments`, `ticket_attachments`, `categories`, `sla_policies`, `sla_breaches`, `knowledge_base_articles`, `knowledge_base_categories`, `service_catalog_items`, `ai_suggestions`, `notifications`. Todas las tenant-scoped tienen `tenant_id` FK con cascade delete.
