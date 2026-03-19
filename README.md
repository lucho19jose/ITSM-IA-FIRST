# AutoService — ITSM AI-First

**AutoService** es una plataforma **open source** de ITSM (IT Service Management) con inteligencia artificial nativa, diseñada para empresas medianas y grandes de cualquier parte del mundo (50+ empleados). Compite directamente con Freshservice y ServiceNow, ofreciendo IA integrada desde el primer día. Incluye soporte nativo para el mercado peruano (precios en soles PEN, cumplimiento Ley 29733, SUNAT), pero puede adaptarse a cualquier región.

> 🌍 **Proyecto open source bajo licencia MIT** — cualquier contribución es bienvenida.
> ☁️ ¿Prefieres no administrar servidores? Consulta la [Versión Cloud (Pro)](#versión-cloud-pro).

---

## Tabla de contenidos

1. [Descripción general](#descripción-general)
2. [Arquitectura](#arquitectura)
3. [Requisitos previos](#requisitos-previos)
4. [Instalación y configuración](#instalación-y-configuración)
   - [Backend (Laravel 11)](#backend-laravel-11)
   - [Frontend (Vue 3 + Quasar)](#frontend-vue-3--quasar)
5. [Variables de entorno](#variables-de-entorno)
6. [Comandos útiles](#comandos-útiles)
7. [Estructura del proyecto](#estructura-del-proyecto)
8. [API Reference](#api-reference)
9. [Roles y permisos](#roles-y-permisos)
10. [Integración con IA](#integración-con-ia)
11. [Características específicas para Perú](#características-específicas-para-perú)
12. [Base de datos](#base-de-datos)
13. [Tests](#tests)
14. [Contribuir](#contribuir)
15. [Versión Cloud (Pro)](#versión-cloud-pro)
16. [Licencia](#licencia)

---

## Descripción general

AutoService provee las siguientes capacidades principales:

- **Gestión de tickets** con ciclo de vida completo (creación, asignación, escalado, cierre).
- **Clasificación automática con IA**: los tickets se categorizan y priorizan usando Claude (Anthropic) con umbral de confianza configurable (> 70 % para auto-aplicación).
- **Sugerencias de respuesta** generadas por IA para agentes.
- **Base de conocimiento (KB)** con generación automática de artículos desde tickets resueltos.
- **Catálogo de servicios** con solicitud de ítems directamente desde el portal.
- **Políticas SLA** con detección de incumplimientos y notificaciones automáticas.
- **Chatbot de autoservicio** por tenant (vía API pública).
- **Portal de usuario final** separado por slug de tenant.
- **Dashboard y reportes** con métricas en tiempo real.
- **Multi-tenancy** single-database con aislamiento por `tenant_id`.
- **Soporte multi-idioma**: español (default) e inglés.

---

## Arquitectura

El proyecto es un **monorepo** con dos proyectos independientes:

```
ITSM-IA-FIRST/
├── backend/   # Laravel 11 — API REST
└── frontend/  # Vue 3 + Quasar 2 — SPA TypeScript
```

| Capa | Tecnología |
|---|---|
| Backend API | PHP 8.2 · Laravel 11 · Laravel Passport (OAuth2) · Laravel Reverb (WebSockets) |
| Base de datos | MySQL 8.0 (single-DB multi-tenant) |
| Cola de trabajos | Database driver (Jobs asíncronos para IA) |
| Frontend | Vue 3 · Quasar 2 · Pinia · vue-i18n · Vite |
| IA | Anthropic Claude API (`claude-sonnet-4-20250514`) |
| Tiempo real | Laravel Echo + Pusher JS |

---

## Requisitos previos

- **PHP** ≥ 8.2 con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- **Composer** ≥ 2.4
- **Node.js** ≥ 22 y **npm**
- **MySQL** ≥ 8.0
- Clave de API de **Anthropic** (para funciones de IA)

> En Windows se recomienda usar [Laragon](https://laragon.org/) con virtual host `autoservice.test` apuntando a `backend/public`.

---

## Instalación y configuración

### Backend (Laravel 11)

```bash
cd backend

# 1. Instalar dependencias PHP
composer install

# 2. Copiar y editar variables de entorno
cp .env.example .env
# Editar .env: DB_*, ANTHROPIC_API_KEY, etc.

# 3. Generar clave de aplicación
php artisan key:generate

# 4. Ejecutar migraciones y seeders (datos demo incluidos)
php artisan migrate:fresh --seed

# 5. Instalar Passport (genera claves OAuth)
php artisan passport:install

# 6. Iniciar servidor de desarrollo
php artisan serve
```

### Frontend (Vue 3 + Quasar)

```bash
cd frontend

# 1. Instalar dependencias Node
npm install

# 2. Copiar y editar variables de entorno
cp .env.example .env
# Ajustar VITE_API_BASE_URL si el backend no corre en http://localhost:8000

# 3. Iniciar servidor de desarrollo (puerto 5174)
npm run dev
```

---

## Variables de entorno

### Backend (`backend/.env`)

| Variable | Descripción | Ejemplo |
|---|---|---|
| `APP_NAME` | Nombre de la aplicación | `AutoService` |
| `APP_URL` | URL base del backend | `http://autoservice.test` |
| `APP_TIMEZONE` | Zona horaria | `America/Lima` |
| `DB_CONNECTION` | Driver de base de datos | `mysql` |
| `DB_HOST` | Host de MySQL | `127.0.0.1` |
| `DB_PORT` | Puerto de MySQL | `3306` |
| `DB_DATABASE` | Nombre de la base de datos | `autoservice_db` |
| `DB_USERNAME` | Usuario de MySQL | `root` |
| `DB_PASSWORD` | Contraseña de MySQL | *(vacío en local)* |
| `QUEUE_CONNECTION` | Driver de colas | `database` |
| `ANTHROPIC_API_KEY` | Clave API de Anthropic (IA) | `sk-ant-...` |
| `BROADCAST_CONNECTION` | Driver de broadcasting | `reverb` |

### Frontend (`frontend/.env`)

| Variable | Descripción | Ejemplo |
|---|---|---|
| `VITE_API_BASE_URL` | URL base de la API | `http://autoservice.test/api/v1` |

---

## Comandos útiles

### Backend (`cd backend/`)

```bash
php artisan serve                     # Servidor de desarrollo
php artisan migrate                   # Ejecutar migraciones pendientes
php artisan migrate:fresh --seed      # Reset completo de BD con datos demo
php artisan db:seed                   # Sólo seeders (categorías, SLA, demo)
php artisan queue:work                # Procesar jobs de IA en cola
php artisan schedule:run              # Ejecutar tareas programadas (SLA check)
php artisan test                      # Suite de tests completa
php artisan test --filter=TicketCrud  # Test específico
./vendor/bin/pint                     # Formatear código (Laravel Pint)
```

### Frontend (`cd frontend/`)

```bash
npm run dev          # Servidor de desarrollo (Vite, puerto 5174)
npm run build        # Build de producción
npm run preview      # Vista previa del build de producción
npm run test         # Tests con Vitest
npm run test:watch   # Tests en modo watch
npx vue-tsc --noEmit # Verificación de tipos TypeScript
```

---

## Estructura del proyecto

```
backend/
├── app/
│   ├── Console/          # Comandos Artisan personalizados
│   ├── Events/           # Eventos (broadcasting en tiempo real)
│   ├── Http/
│   │   ├── Controllers/Api/V1/   # Controladores REST
│   │   ├── Middleware/           # Auth, Tenant, CheckRole, SuperAdmin
│   │   └── Resources/            # API Resources (transformación de respuestas)
│   ├── Jobs/             # Jobs asíncronos (ClassifyTicketJob, etc.)
│   ├── Models/           # Modelos Eloquent con BelongsToTenant trait
│   ├── Policies/         # Laravel Policies por modelo
│   ├── Providers/        # Service Providers
│   └── Services/
│       └── Ai/           # ClaudeClient, TicketClassifier, ResponseSuggester,
│                         # KbGenerator, ChatbotService, SlaPredictorService
├── config/
│   └── ai.php            # Configuración del proveedor IA
├── database/
│   ├── migrations/       # Migraciones de BD
│   └── seeders/          # Seeders con datos demo
├── resources/
│   └── prompts/          # Plantillas Blade para prompts de IA
└── routes/
    └── api.php           # Todas las rutas bajo /api/v1/

frontend/
└── src/
    ├── api/              # Módulos por recurso (tickets.ts, kb.ts, etc.)
    ├── components/       # Componentes reutilizables
    ├── composables/      # Composables Vue
    ├── i18n/             # Traducciones (es/, en/)
    ├── layouts/          # Layouts de la aplicación
    ├── pages/            # Páginas por ruta
    ├── router/           # Definición de rutas y guards
    ├── stores/           # Stores Pinia
    ├── types/            # Tipos TypeScript globales
    └── utils/
        ├── api.ts        # Instancia Axios centralizada
        └── currency.ts   # Helpers para moneda PEN
```

---

## API Reference

Todas las rutas están prefijadas con `/api/v1/`. La autenticación usa tokens OAuth2 Bearer (Laravel Passport).

### Autenticación

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/auth/register` | Registro de nuevo usuario/tenant |
| `POST` | `/auth/login` | Login, retorna token de acceso |
| `POST` | `/auth/logout` | Cierre de sesión (revoca token) |
| `GET` | `/auth/me` | Datos del usuario autenticado |

### Tickets

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/tickets` | Listar tickets (filtros, paginación) |
| `POST` | `/tickets` | Crear ticket |
| `GET` | `/tickets/{id}` | Detalle de ticket |
| `PUT` | `/tickets/{id}` | Actualizar ticket |
| `DELETE` | `/tickets/{id}` | Eliminar ticket |
| `POST` | `/tickets/{id}/assign` | Asignar agente |
| `POST` | `/tickets/{id}/close` | Cerrar ticket |
| `POST` | `/tickets/{id}/reopen` | Reabrir ticket |
| `POST` | `/tickets/{id}/comments` | Agregar comentario |
| `POST` | `/tickets/{id}/attachments` | Subir adjuntos |
| `PUT` | `/tickets/bulk-update` | Actualización masiva *(admin/agent)* |
| `POST` | `/tickets/export` | Exportar tickets *(admin/agent)* |

### IA

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/tickets/{id}/classify` | Clasificar ticket con IA |
| `POST` | `/tickets/{id}/suggest-response` | Sugerir respuesta con IA |
| `POST` | `/tickets/{id}/generate-kb` | Generar artículo KB desde ticket |
| `POST` | `/ai/improve-text` | Mejorar texto con IA |

### Portal público (por tenant)

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/portal/{slug}/info` | Info del tenant |
| `POST` | `/portal/{slug}/login` | Login de usuario final |
| `POST` | `/portal/{slug}/register` | Registro de usuario final |
| `GET` | `/portal/{slug}/kb/articles` | Artículos KB públicos |
| `GET` | `/portal/{slug}/catalog` | Catálogo de servicios |

### Chatbot

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/chatbot/{slug}/message` | Enviar mensaje al chatbot |
| `POST` | `/chatbot/{slug}/create-ticket` | Crear ticket desde chatbot |

---

## Roles y permisos

| Rol | Permisos |
|---|---|
| `admin` | Acceso total: usuarios, configuración, SLA, categorías, KB, tickets, reportes, super-admin routes |
| `agent` | Gestión de tickets asignados, KB, sugerencias IA |
| `end_user` | Solo sus propios tickets y artículos KB públicos |
| `super_admin` | Panel de gestión de tenants (plataforma) |

Los roles se verifican con el middleware `CheckRole`, Laravel Policies y guards de router en el frontend.

---

## Integración con IA

- **Proveedor**: Anthropic Claude (`claude-sonnet-4-20250514`)
- **Configuración**: `backend/config/ai.php` (API key, modelo, rate limits)
- **Flujo de clasificación**:
  1. Al crear/actualizar un ticket, se despacha `ClassifyTicketJob` de forma asíncrona.
  2. `TicketClassifier` llama a `ClaudeClient` con el prompt en `resources/prompts/classify_ticket.blade.php`.
  3. Si la confianza > 70 %, los campos (categoría, prioridad) se aplican automáticamente.
  4. La sugerencia se guarda en la tabla `ai_suggestions`.
- **Servicios disponibles**:
  - `ClaudeClient` — cliente HTTP hacia la API de Anthropic
  - `TicketClassifier` — clasificación de categoría y prioridad
  - `ResponseSuggester` — sugerencia de respuesta para agentes
  - `KbGenerator` — generación de artículos de Knowledge Base
  - `ChatbotService` — conversación de autoservicio
  - `SlaPredictorService` — predicción de riesgo de incumplimiento SLA

---

## Características específicas para Perú

- **RUC**: validación de 11 dígitos (SUNAT) almacenado en el modelo `Tenant`.
- **Moneda**: soles peruanos (PEN). Helpers de formato en `frontend/src/utils/currency.ts`.
- **Zona horaria**: `America/Lima` por defecto (`APP_TIMEZONE`).
- **Ley 29733** (Protección de Datos Personales): consentimiento explícito en registro, API de exportación y eliminación de datos del usuario.
- **Facturación electrónica**: estructura preparada con placeholder SUNAT CPE para integración futura.

---

## Base de datos

Motor: **MySQL 8.0**, base de datos `autoservice_db`.

### Tablas principales

| Tabla | Descripción |
|---|---|
| `tenants` | Organizaciones (multi-tenant) |
| `users` | Usuarios con `tenant_id`, rol y datos de perfil |
| `tickets` | Tickets de soporte con estado, prioridad y SLA |
| `ticket_comments` | Comentarios y respuestas de tickets |
| `ticket_attachments` | Archivos adjuntos de tickets y comentarios |
| `ticket_form_fields` | Campos personalizables del formulario de tickets |
| `ticket_views` | Filtros guardados por usuario |
| `categories` | Categorías de tickets por tenant |
| `departments` | Departamentos por tenant |
| `sla_policies` | Políticas SLA con tiempos de respuesta/resolución |
| `sla_breaches` | Registro de incumplimientos SLA |
| `knowledge_base_categories` | Categorías de la base de conocimiento |
| `knowledge_base_articles` | Artículos KB con soporte de votos de utilidad |
| `service_catalog_items` | Ítems del catálogo de servicios |
| `ai_suggestions` | Log de todas las interacciones con la IA |
| `notifications` | Notificaciones en la aplicación |

Todos los modelos con scope de tenant usan el trait `BelongsToTenant`, que aplica `TenantScope` globalmente y asigna `tenant_id` automáticamente al crear registros.

---

## Tests

### Backend

```bash
cd backend
php artisan test                      # Todos los tests
php artisan test --filter=TicketCrud  # Tests específicos
```

Los tests se encuentran en `backend/tests/` usando PHPUnit.

### Frontend

```bash
cd frontend
npm run test         # Vitest (run once)
npm run test:watch   # Vitest en modo watch
```

---

## Contribuir

AutoService es un proyecto **open source** y **toda contribución es bienvenida**, sin importar tu ubicación o nivel de experiencia. Puedes contribuir con código, documentación, traducciones, reportes de bugs, ideas o mejoras.

### ¿Cómo contribuir?

1. Haz fork del repositorio y crea una rama desde `main`.
2. Asegúrate de seguir los patrones del proyecto:
   - Backend: PSR-4, formato con `./vendor/bin/pint`.
   - Frontend: Composition API con `<script setup lang="ts">`, nunca Options API.
   - Imports de API en frontend sólo desde `src/api/`, nunca llamadas directas a Axios.
3. Escribe o actualiza los tests correspondientes.
4. Abre un Pull Request con descripción clara de los cambios.

### Ideas de contribución

- 🌐 Traducciones a nuevos idiomas
- 🐛 Reporte y corrección de bugs
- ✨ Nuevas integraciones con proveedores de IA
- 📖 Mejoras a la documentación
- 🧪 Ampliar la cobertura de tests
- 🔧 Integraciones con herramientas externas (Jira, Slack, Teams, etc.)

---

## Versión Cloud (Pro)

¿No quieres lidiar con servidores, infraestructura ni configuraciones? Existe una **versión de pago totalmente gestionada en la nube** que te da todo listo para usar desde el primer día.

### ¿Qué incluye la versión Pro Cloud?

| Componente | Detalle |
|---|---|
| 🖥️ **Infraestructura** | Servidores dedicados de alta disponibilidad, escalado automático |
| 🤖 **IA lista para usar** | API de Anthropic Claude configurada y optimizada, sin coste adicional por uso de IA |
| 🗄️ **Base de datos gestionada** | MySQL en la nube con backups automáticos, réplicas y failover |
| 🔒 **Seguridad y compliance** | SSL/TLS, cifrado en reposo, auditorías de seguridad |
| 📧 **Email y notificaciones** | SMTP gestionado, integración con proveedores de correo transaccional |
| 🔄 **Actualizaciones automáticas** | Siempre en la última versión estable, sin downtime |
| 📊 **Monitoreo y alertas** | Dashboard de salud del sistema, alertas proactivas |
| 🛠️ **Soporte técnico** | Soporte dedicado con SLA garantizado |
| 🌎 **Multi-región** | Elección de región de datos (Latinoamérica, EE.UU., Europa) |

### ¿Para quién es la versión Pro?

- Empresas que **no quieren gestionar infraestructura** internamente.
- Equipos que necesitan **onboarding rápido** (tu instancia lista en minutos).
- Organizaciones con **requisitos de compliance** que necesitan SLAs garantizados.
- Empresas **grandes** que requieren escalabilidad sin límite.

> 📩 **¿Te interesa la versión Cloud?** Escríbenos a través de los Issues del repositorio o contacta al equipo del proyecto para obtener información sobre precios y planes.

---

## Licencia

AutoService se distribuye bajo la **licencia MIT**.

### ¿Qué significa la licencia MIT?

La licencia MIT es una de las licencias de software libre más permisivas que existen. En términos simples:

**✅ Puedes:**
- Usar el software de forma **gratuita**, para cualquier propósito (personal, comercial, educativo, etc.)
- **Modificar** el código fuente como necesites
- **Distribuir** copias del software original o modificado
- **Incorporar** este software en proyectos propietarios o comerciales
- **Sublicenciar** el software

**📋 Solo debes:**
- Incluir el aviso de copyright y el texto de la licencia MIT en todas las copias o porciones sustanciales del software

**❌ No incluye:**
- Garantía de ningún tipo (el software se entrega "tal cual")
- Responsabilidad de los autores por daños o perjuicios derivados del uso

```
MIT License

Copyright (c) 2025 AutoService Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
