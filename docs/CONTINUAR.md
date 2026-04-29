# Sinapsa — guía para retomar el trabajo

> Documento vivo. Si vuelves al proyecto en 3 meses (o lo retoma alguien nuevo), **lee esto primero**.

Última actualización: **2026-04-29** · Estado: backend + frontend funcionales en local con Connect-as-a-Service end-to-end.

---

## TL;DR — qué es Sinapsa hoy

**Sinapsa** es una plataforma **Tech Provider** ante Meta para WhatsApp Cloud API, Instagram DM y Facebook Messenger.

Modelo de negocio:
- NO vende suscripciones SaaS multi-empresa.
- Sus **clientes SaaS** (CRMs, bots, los SaaS propios del owner) **embeben un botón JS** de Sinapsa en sus apps.
- Los **usuarios finales** del cliente SaaS clican → popup oficial Meta usando la app de Sinapsa → Embedded Signup → canal queda en Sinapsa.
- Cliente SaaS NO toca Meta. NO hace App Review. NO maneja tokens.
- Cobro futuro = markup per-mensaje (`usage_meters` ya está cableado), NO suscripciones.

Stack:

| Capa | Tech |
|---|---|
| Backend | Laravel 13.6 + PHP 8.3 + Sanctum + Horizon + Reverb + Spatie + Pest |
| DB | PostgreSQL 16 (sin PostGIS, particionado mensual `messages` y `webhook_deliveries`) |
| Cache/Queue | Redis 7 |
| Frontend | Next.js 16.2 + React 19 + TS 5.9 + Tailwind 4 + Hugeicons + Euclid Circular B |
| Realtime | Laravel Reverb sobre Redis |
| Object storage | Cloudflare R2 (prod) / disk local (dev) |

Repo: `git@github.com:opensyscl/sinapsa.git` rama `main`.

---

## Cómo retomar en local (zero-to-running)

### Pre-requisitos en tu máquina
- Docker + Docker Compose v2.35+
- Node 20+ con corepack (`corepack enable`)
- pnpm 9+ (vendrá con corepack)
- PHP 8.3 + Composer en host (opcional, también se puede via container)

### Setup completo (10 minutos)

```bash
git clone git@github.com:opensyscl/sinapsa.git
cd sinapsa

# 1. Variables de entorno
cp .env.example .env
cp .env.example backend/.env
# Genera APP_KEY (sin esto Laravel no arranca)
docker compose run --rm php php artisan key:generate

# 2. Levantar la infra
docker compose up -d postgres redis mailpit php nginx

# 3. Backend: instalar deps + migrar
docker exec one-rrss-php composer install
docker exec one-rrss-php php artisan migrate

# 4. Frontend: en otro terminal
cd frontend
pnpm install
cp ../.env.example .env.local   # luego ajusta NEXT_PUBLIC_*
pnpm dev --port 3002

# 5. Reverb (en otra terminal o como container)
docker compose up -d reverb
```

Acceder:
- Frontend: http://localhost:3002
- API: http://localhost:48000/api/health
- Mailpit: http://localhost:48025
- Reverb WebSocket: ws://localhost:48080

### Crear tu primer workspace + token de prueba

```bash
# 1. Registra un workspace + user owner desde la UI:
#    Browser → http://localhost:3002/registro

# 2. Login (token Sanctum se persiste en localStorage)
#    Browser → http://localhost:3002/login

# 3. Crear API token (sk_live_xxx) desde /desarrolladores
#    O via tinker:
docker exec one-rrss-php php artisan tinker --execute='
echo App\Models\ApiToken::issue(1, "Demo", ["*"], 1, "test")["plain"];
'

# 4. Crear un canal WhatsApp de prueba (sin Meta App real)
docker exec one-rrss-php php artisan channels:create-test-whatsapp <slug-del-workspace>

# 5. Simular un webhook entrante de WhatsApp
docker exec one-rrss-php php artisan messages:simulate-webhook incoming-text.json --queue
```

Verás el mensaje en `/inbox`.

---

## Qué está hecho (fases ya cerradas)

Detalle completo en [architecture.md](../architecture.md) sección 20. Resumen:

| Fase | Estado | Qué entrega |
|---|---|---|
| **0** Setup | ✅ | Esqueleto Laravel 13 + Next 16 + Docker + Hugeicons + Euclid + tema sobrio |
| **1** Auth | ✅ | Sanctum register/login/me/logout, multi-tenant scope, frontend cableado |
| **2** Modelo mensajería | ✅ | channels/contacts/conversations/messages (particionadas) + adapter pattern + WA Cloud completo |
| **3** Embedded Signup WA backend | ✅ | `MetaEmbeddedSignupService`, sync templates, healthCheck, manual connect dev |
| **4** Bandeja `/inbox` | ✅ | 3 columnas, realtime Reverb, composer, status glyphs |
| **5** API pública SaaS | ✅ | `sk_live_xxx` con scopes, errores Stripe-like, `Idempotency-Key`, POST `/api/v1/messages` |
| **6** Webhooks salientes | ✅ | HMAC, retries, DLQ, replay UI, receiver dev local |
| **7** Templates WA managed | ✅ | Sync bidireccional, builder UI, status updates por webhook |
| **8** IG + Messenger adapters | ✅ | Los 3 canales operativos, controller polimórfico, fixtures E2E |
| **9** Connect-as-a-Service | ✅ | ConnectSession + JWT + Hosted Page + JS SDK; pivote a Tech Provider puro (sin trial/planes) |
| **+ docs viewer** | ✅ | `/docs/meta-setup` renderiza el manual MD |

**Pivote producto importante (2026-04-29):** Sinapsa pivota de "SaaS multi-empresa con planes" a "Tech Provider Meta puro". Todo el código de planes/trial/Stripe se ELIMINÓ. Si vuelves al proyecto y ves referencias a "trial 14d" u "PlanGate", es de antes del pivote — todo limpio en `main`.

---

## Lo que falta para producción real

### Bloqueante (acción tuya, no de código)
1. **Crear Business Manager** + verificación de empresa Meta → 1-3 días
2. **Crear app Meta tipo Business** + activar productos WA/IG/Messenger
3. **Configurar 3 Embedded Signups** (uno por canal) → 3 `config_id`
4. **Suscribir webhooks** a tu URL prod (`https://api.sinapsa.app/webhooks/meta/{whatsapp,instagram,messenger}`)
5. **App Review** para los 3 bloques de permisos → 1-3 semanas cada uno (en paralelo)
6. **Configurar `.env` de prod** con `META_APP_ID`, `META_APP_SECRET`, `META_*_EMBEDDED_SIGNUP_CONFIG_ID`
7. **Política privacidad + ToS** públicas en `sinapsa.app/privacy` y `sinapsa.app/terms`

Manual paso a paso: [META_SETUP.md](META_SETUP.md). También accesible via UI en `/docs/meta-setup`.

### Mejoras de código pendientes (TODO)

1. **Modo dev fallback en `ConnectSessionController::complete`**: si `META_APP_ID` está vacío Y `app()->environment(['local','staging'])`, aceptar `access_token` directo en el body (delegar a `connectManual()`). Permitiría testear el SDK end-to-end SIN tener Meta App Review aprobada todavía. **Prioridad alta** — clave para vender al primer cliente SaaS antes de pasar Meta.
2. **IG y Messenger Embedded Signup en `complete()`**: ahora solo soporta `channel_type=whatsapp`. Replicar para los otros 2 con sus scopes.
3. **Página `/uso`** en el dashboard que lea `usage_meters` y muestre tráfico por mes/canal/kind (inbound/outbound). **Prioridad alta** cuando empieces a cobrar markup.
4. **Endpoints públicos `/api/v1/contacts, /conversations, /channels`** — el cliente SaaS hoy solo puede `POST /messages` y `GET /messages`. Para gestionar contactos y leer conversaciones desde su backend, falta el complemento. Prioridad media.
5. **Restricción de `event.origin`** en `frontend/public/sdk.js` — ahora acepta `*` en dev. En prod restringir a `ORIGIN === 'https://app.sinapsa.app'`.
6. **Worker daemon Horizon** en producción para procesar las colas (`webhooks-out`, `outbound`, `inbound`). En dev usamos `queue:work` puntual.
7. **Sub-tenancy multi-nivel**: si un cliente SaaS pide que sus 50 clientes finales tengan separación interna (no solo workspace), añadir concepto "Tenant" dentro del workspace. NO hacer hasta que un cliente lo pida.
8. **Rate limit por API token** además del throttle global (ahora solo el throttle Laravel default). Útil cuando un cliente SaaS abusa.
9. **Logs de API request/response** persistidos para debugging desde el dashboard del cliente.
10. **SDKs server-side** (Node/PHP/Python) para los clientes SaaS. NO hacer hasta que 3 clientes los pidan.

---

## Cosas a NO romper (decisiones consolidadas)

### Producto
- **Sinapsa NO vende suscripciones**. Es Tech Provider puro. NO añadir tablas `plans`, `subscriptions`, NO añadir Stripe Subscriptions. Cuando se cobre, será **Stripe Invoicing** o markup en factura externa, leyendo de `usage_meters`.
- **Cliente final = workspace en Sinapsa**. NO añadir un nivel "tenant" dentro del workspace hasta que un cliente real lo pida.
- **Bandeja `/inbox` se mantiene** como dogfood / debugging tool. NO borrarla aunque los clientes SaaS no la usen — sirve para que el equipo Sinapsa vea qué pasa en los workspaces.

### Arquitectura
- **Multi-tenant single-DB con `workspace_id`** + `BelongsToWorkspace` trait + `WorkspaceScope` global. NO migrar a multi-DB hasta tener 50k msg/día por workspace top.
- **Adapter pattern por canal**. Fuera de los adapters NUNCA mirar el payload Meta directo. Para añadir Telegram = nuevo adapter en `App\Channels\Telegram\` + 1 línea en `ChannelAdapterRegistry`.
- **Outbox pattern** para llamadas a Meta y webhooks salientes. NUNCA HTTP en plena petición.
- **Idempotencia obligatoria** en POSTs (`Idempotency-Key` header → Redis 24h).
- **Encryption at-rest** de access_tokens Meta con `Crypt::encryptString`.
- **Postgres native partitioning mensual** en `messages` y `webhook_deliveries`. Las particiones futuras se crean a mano por ahora — añadir job mensual cuando duela.

### Frontend
- **Hugeicons obligatorio**. Nunca Lucide, Heroicons, SVG random. Verificar siempre con grep antes de usar — algunos iconos no existen (lista en [CLAUDE.md](../CLAUDE.md)).
- **Euclid Circular B obligatorio**. Cargada vía `@font-face` en `globals.css`. Nada de Geist/Inter/Arial.
- **Cards: `rounded-3xl border border-border`**. NUNCA `rounded-2xl shadow-sm`.
- **Botones primarios negros**, sin gradientes ni AI button look.
- **Formularios**: RHF + Zod con `z.input/z.output` cuando uses `z.coerce`.

### Backend
- **API tokens del SaaS** son DISTINTOS de Sanctum. Modelo propio `ApiToken` con scopes, formato `sk_live_xxx`. Sanctum SOLO para humanos del dashboard.
- **Errores tipados estilo Stripe** en endpoints `/api/v1/*` públicos. `ApiException` con `type/code/message/param`. NO devolver 500 con stacktrace.
- **`ApiException::$errorCode`**, NO `$code` — `\Exception::$code` no es readonly y choca.
- **Auth failures** en `/api/*` deben devolver JSON 401 (handler explícito en `bootstrap/app.php`).
- **`ShouldBroadcastNow`** vs `ShouldBroadcast`: usa `Now` para hot-path realtime (chat). El otro encola y añade latencia.
- **WorkspaceScope respeta `auth()->check()`**: en webhooks/jobs sin auth pasa sin filtrar (correcto).

---

## Estructura de archivos clave

```
sinapsa/
├── plan.md                              # Brief original — NO editar
├── architecture.md                      # 24 secciones — fuente verdad del diseño
├── CLAUDE.md                            # Convenciones para agentes IA
├── docs/
│   ├── META_SETUP.md                    # Manual operativo Meta
│   └── CONTINUAR.md                     # ESTE archivo
├── docker-compose.yml                   # 6 servicios (postgres, redis, mailpit, php, nginx, reverb)
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── fonts/                               # Euclid Circular B (5 woff2)
├── backend/                             # Laravel 13.6
│   ├── app/
│   │   ├── Channels/                    # Adapter pattern Meta
│   │   │   ├── Contracts/ChannelAdapter.php
│   │   │   ├── ChannelAdapterRegistry.php
│   │   │   ├── DTO/                     # Spatie Data: NormalizedMessage, etc
│   │   │   ├── Enums/                   # ChannelType, MessageDirection, MessageType
│   │   │   ├── Support/                 # MetaGraphClient, MetaSignatureVerifier
│   │   │   ├── WhatsAppCloud/           # Adapter + parser + builder + services
│   │   │   ├── Instagram/               # ídem
│   │   │   └── Messenger/               # ídem
│   │   ├── Connect/
│   │   │   └── ConnectSessionTokenService.php  # JWT HS256 hand-crafted
│   │   ├── Events/                      # MessageReceived, MessageStatusUpdated (ShouldBroadcastNow)
│   │   ├── Exceptions/ApiException.php  # Stripe-like errors
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/V1/              # Endpoints internos + públicos
│   │   │   │   └── Webhooks/MetaWebhookController.php  # Polimórfico (3 canales)
│   │   │   ├── Middleware/              # IdempotencyKey, RequireApiScope
│   │   │   ├── Requests/Auth/
│   │   │   └── Resources/               # Resources + filtrado de secrets
│   │   ├── Jobs/                        # Process inbound, send outbound, deliver webhooks, sync templates, refresh tokens
│   │   ├── Models/                      # Workspace, User, Channel, Contact, Conversation, Message, ApiToken, etc
│   │   │   ├── Concerns/BelongsToWorkspace.php
│   │   │   └── Scopes/WorkspaceScope.php
│   │   ├── Providers/AppServiceProvider.php  # Auth::viaRequest('api-token')
│   │   └── Webhooks/                    # OutboundEventDispatcher, WebhookSigner, EventPayloadBuilder
│   ├── bootstrap/app.php                # Withrouting, withBroadcasting, withExceptions
│   ├── config/sinapsa.php               # Settings producto
│   ├── database/migrations/             # Postgres + particiones
│   ├── routes/
│   │   ├── api.php                      # /api/v1/* + /api/__webhook-test/{slug}
│   │   ├── webhooks.php                 # /webhooks/meta/{type} (HMAC auth)
│   │   └── channels.php                 # Broadcast workspace.{id}.inbox
│   └── tests/Fixtures/meta/             # Payloads reales para SimulateMetaWebhook
└── frontend/                            # Next.js 16
    ├── public/
    │   ├── fonts/                       # Euclid woff2
    │   └── sdk.js                       # JS SDK Connect-as-a-Service
    └── src/
        ├── app/
        │   ├── (auth)/{login,registro}/page.tsx
        │   ├── (app)/                   # Layout con AuthGuard
        │   │   ├── dashboard/
        │   │   ├── inbox/
        │   │   ├── canales/
        │   │   ├── plantillas/
        │   │   ├── desarrolladores/
        │   │   └── docs/                # Markdown viewer
        │   ├── connect/page.tsx         # Hosted Connect Page (público, sin AuthGuard)
        │   └── layout.tsx               # Root con Providers (TanStack + Sonner)
        ├── components/
        │   ├── AuthGuard.tsx
        │   ├── Topbar.tsx               # Nav principal
        │   ├── Providers.tsx
        │   ├── ui/                      # Button, Card, Input, Badge, Dialog
        │   ├── inbox/                   # ConversationList, MessageThread, Composer, Bubble, ContactPanel
        │   ├── channels/ConnectChannelDialog.tsx
        │   ├── developers/              # CreateTokenDialog, CreateWebhookDialog, DeliveriesDialog
        │   ├── templates/CreateTemplateDialog.tsx
        │   └── docs/MarkdownView.tsx
        ├── lib/
        │   ├── api.ts                   # Axios + bearer token
        │   ├── echo.ts                  # Laravel Echo + Pusher
        │   ├── utils.ts                 # cn() helper
        │   ├── slug.ts                  # slugify
        │   └── queries/                 # TanStack Query hooks
        └── store/auth.ts                # Zustand persist
```

---

## Comandos cheatsheet

```bash
# === DOCKER ===
docker compose up -d                                # Levanta todo
docker compose down                                 # Para todo
docker compose logs -f php                          # Logs Laravel
docker compose logs -f reverb                       # Logs WS

# === BACKEND ===
docker exec one-rrss-php composer install
docker exec one-rrss-php php artisan migrate
docker exec one-rrss-php php artisan migrate:fresh   # ⚠️ borra DB
docker exec one-rrss-php php artisan tinker
docker exec one-rrss-php php artisan optimize:clear  # Tras cambiar config/routes

# === COLAS (en producción usa Horizon) ===
docker exec one-rrss-php php artisan queue:work --queue=inbound --stop-when-empty
docker exec one-rrss-php php artisan queue:work --queue=outbound --stop-when-empty
docker exec one-rrss-php php artisan queue:work --queue=webhooks-out --stop-when-empty

# === DEV TOOLS ===
docker exec one-rrss-php php artisan channels:create-test-whatsapp <workspace_slug>
docker exec one-rrss-php php artisan messages:simulate-webhook incoming-text.json --queue
docker exec one-rrss-php php artisan messages:simulate-webhook --channel=instagram incoming-text.json --queue
docker exec one-rrss-php php artisan messages:simulate-webhook --channel=messenger incoming-text.json --queue
docker exec one-rrss-php php artisan messages:simulate-webhook template-status-approved.json --queue

# === DB ===
docker exec one-rrss-postgres psql -U sinapsa -d sinapsa
# Comandos SQL útiles:
#   SELECT id, type, status, display_name FROM channels;
#   SELECT id, period, kind, channel_type, count FROM usage_meters;
#   SELECT * FROM connect_sessions ORDER BY id DESC LIMIT 5;
#   SELECT id, status, attempt, response_status FROM webhook_deliveries ORDER BY id DESC LIMIT 10;

# === FRONTEND ===
cd frontend
pnpm dev --port 3002       # Dev server
pnpm exec tsc --noEmit     # TS check sin emitir
pnpm build                  # Build prod (verifica que compila)

# === GIT ===
git status
git log --oneline -20
git push origin main
```

---

## Aprendizajes ya en el código (NO repetir)

Lecciones cazadas durante el desarrollo. Si te topas con una que no está aquí, añádela.

### Backend
- **Postgres particionado**: cualquier UNIQUE constraint debe incluir la clave de particionado (`created_at`). Por eso `messages.external_id` no es UNIQUE — la idempotencia se hace en código.
- **Eloquent `Model::create()` ignora silenciosamente claves no `$fillable`** — no intentes setear `created_at` ahí, usa atributo directo + `save()`.
- **`\Exception::$code` no es readonly** — al extender, renombra a `$errorCode` o similar.
- **`Auth::viaRequest('driver-name', closure)`** es la forma más limpia de añadir guards custom en Laravel 11+.
- **`new UserMiniResource(null)` revienta** si la relación está cargada pero el FK es null. Envolver con closure: `$this->whenLoaded('assignedTo', fn () => $this->assignedTo ? new UserMiniResource($this->assignedTo) : null)`.
- **Auth failures devuelven HTML por defecto en `/api/*`** — registrar handler explícito para `AuthenticationException` que matche `$request->is('api/*')` y devuelva JSON.
- **`REVERB_HOST=0.0.0.0`** funciona para BIND pero NO para CONNECT desde otro container. Usar `reverb` (nombre del servicio docker) en `.env` del backend; el bind a 0.0.0.0 va por `--host=0.0.0.0` en el comando del compose.
- **`ShouldBroadcastNow` vs `ShouldBroadcast`**: usar `Now` para hot-path realtime; el otro encola y añade latencia.
- **`queue:work --once --stop-when-empty`** es engañoso — `--once` significa "una iteración del worker loop", puede saltar jobs si llegan durante el procesamiento. Usar solo `--stop-when-empty` para drenar.
- **Postgres UPSERT atómico** con `INSERT ... ON CONFLICT DO UPDATE SET col = EXCLUDED.col + ...` para counters concurrentes (jobs).
- **`extras.sessionInfoVersion: 3`** en FB.login es lo que hace que Meta devuelva `phone_number_id+waba_id` en el callback (sin esto solo recibes el code).
- **PHP foreach por referencia en arrays profundamente anidados NO siempre propaga**. Usar reasignación por índices: `$payload['entry'][$ei]['changes'][$ci]['value']['messages'][$mi]['x'] = $y;`.

### Frontend
- **Suspense obligatorio en Next 16** alrededor de componentes que usen `useSearchParams` — sin él, build prerender falla.
- **Hugeicons sin sufijo numérico**: `CodeIcon` ✓, `Code01Icon` ✗. `ConnectIcon` ✓, `Connect01Icon` ✗. **Verificar SIEMPRE** con grep antes de usar.
- **`create-next-app` deja un `.git` embebido** en el dir frontend si lo creas dentro de un repo. Borra `frontend/.git` antes del primer commit del monorepo.
- **`pnpm dev` corre en host**, no recibe envs del docker-compose. Necesita `frontend/.env.local` con `NEXT_PUBLIC_*`.
- **Edits que parecen exitosos pero no aplican**: cuando se pasa `old_string` a Edit pero el archivo no se ha leído en la sesión actual, el Edit dice "OK" pero no cambia nada. Si tinker muestra estado viejo, re-Read el archivo y Edit otra vez.

### Docker / Infra
- **Docker rechaza puertos >65535** (límite TCP). El proyecto usa puertos `4xxxx`. RSV usa `5xxxx`.
- **`docker compose` requiere estar en el directorio del repo** — desde el padre da "no configuration file provided".
- **`next-server` worker hijo sigue vivo** cuando matas `next dev` — hay que matarlo por PID si quieres reciclar el puerto 3002.

---

## Próximo paso recomendado cuando vuelvas

Por orden de impacto:

1. **Modo dev fallback en `/connect-sessions/{token}/complete`** (1 día) → desbloquea testear el SDK con clientes reales **sin** esperar Meta App Review.
2. **Página `/uso`** que lea `usage_meters` (medio día) → te permite ver tráfico y decidir cómo cobrar.
3. **Endpoints públicos `/api/v1/{contacts,conversations,channels,templates}`** (1-2 días) → cierra el contrato de la API pública. Sin esto, los clientes SaaS solo pueden enviar mensajes y leerlos, no gestionar contactos.
4. **Crear app Meta + verificación empresa + Embedded Signup configs** (3-5 días, en paralelo con código) → bloqueante para producción.
5. **Iniciar Meta App Review** (1-3 sem espera, hazlo lo antes posible).
6. **README público** del proyecto orientado a clientes SaaS, con quickstart del SDK + `curl` examples + lista de endpoints. Markdown listo para copiar a una landing.

---

## Glosario rápido

| Término | Qué es |
|---|---|
| **Workspace** | Una "cuenta" en Sinapsa. Para nosotros: un cliente SaaS de Sinapsa, o uno de tus SaaS propios |
| **Channel** | Un número WA / cuenta IG / página FB conectada a un workspace |
| **Tech Provider** | El rol que Sinapsa juega ante Meta. Permite a clientes finales conectar Meta usando tu app sin crear la suya |
| **Connect Session** | Token JWT corto (15 min) que autoriza a un usuario final a conectar su Meta vía la Hosted Page |
| **Hosted Connect Page** | La página `/connect?token=...` que sirve Sinapsa donde se dispara el popup Embedded Signup |
| **JS SDK** | El `sdk.js` que tus clientes SaaS embeben en sus apps. Expone `Sinapsa.connect(...)` |
| **API Token** | El `sk_live_xxx` que un cliente SaaS usa para llamar a la API de Sinapsa desde su backend |
| **WABA** | WhatsApp Business Account. Una cuenta WA que puede tener varios números |
| **Embedded Signup** | El popup oficial de Meta que muestra al usuario final para conectar su WA/IG/FB |
| **Outbox pattern** | Persistir el evento en DB antes de hacer HTTP, y dejar que un job se encargue del transporte con retries |
| **Usage meter** | Contador (workspace, mes, kind, channel_type) que se incrementa en cada mensaje. Para futuro cobro |

---

## Si te atascas

1. Lee primero `architecture.md` (el diseño) y `META_SETUP.md` (Meta side).
2. Revisa los aprendizajes arriba — el 80% de los errores ya están listados.
3. Mira git log: `git log --oneline -50` da una idea de la evolución.
4. La memoria del agente IA guarda contexto: `~/.claude/projects/-home-jos-josbert-dev/memory/project_one_rrss.md` (si trabajas con Claude Code).

> Si añades algo significativo, **actualiza este archivo**. Es lo que va a salvar tiempo a tu yo del futuro.
