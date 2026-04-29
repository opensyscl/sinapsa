# Sinapsa — guía para agentes

> Codename `one-rrss`. Pasarela omnicanal Meta (WhatsApp Cloud + IG DM + FB Messenger) con API y webhooks unificados, multi-empresa.

- **Brief original:** [plan.md](plan.md) — no editar.
- **Arquitectura completa:** [architecture.md](architecture.md) — 24 secciones, fuente de verdad del diseño.
- **Convenciones frontend:** [frontend/AGENTS.md](frontend/AGENTS.md).

## Stack

| Capa | Tech |
|---|---|
| Backend | Laravel 13.6 + PHP 8.3 + Sanctum + Horizon + Reverb + Spatie (permission/activitylog/data/medialibrary) + Pest |
| DB | PostgreSQL 16 (sin PostGIS, particionado mensual en `messages` y `webhook_deliveries`) |
| Cache/Queue | Redis 7 |
| Frontend | Next.js 16.2 + React 19 + TS 5.9 + Tailwind 4 + Hugeicons + Euclid Circular B |
| Realtime | Laravel Reverb |
| Object storage | Cloudflare R2 (prod) / disk local (dev) |

## Puertos Docker (host → container)

| Servicio | Host | Container |
|---|---|---|
| Postgres | 45432 | 5432 |
| Redis | 46379 | 6379 |
| Mailpit UI | 48025 | 8025 |
| Mailpit SMTP | 41025 | 1025 |
| Reverb (WS) | 48080 | 8080 |
| Nginx (API) | 48000 | 80 |
| Frontend dev | 3002 | 3000 |

> Puertos `4xxxx` (todos <65535, límite TCP). `rsv` ocupa `5xxxx`.

## Comandos frecuentes

```bash
# Levantar todo
docker compose up -d

# Backend
docker exec one-rrss-php php artisan migrate
docker exec one-rrss-php php artisan tinker
docker exec one-rrss-php php artisan horizon
docker exec one-rrss-php php artisan reverb:start --host=0.0.0.0 --port=8080

# Frontend (desde el host, más rápido que dentro del container)
cd frontend && pnpm dev   # corre en :3002

# Logs
docker compose logs -f php
docker compose logs -f nginx
```

## Multi-tenancy (idéntico a real-state-valencia)

- **Single-DB con `workspace_id`** en cada tabla tenant.
- Trait [App\Models\Concerns\BelongsToWorkspace](backend/app/Models/Concerns/BelongsToWorkspace.php) auto-rellena `workspace_id` al crear y registra el scope.
- Scope global [App\Models\Scopes\WorkspaceScope](backend/app/Models/Scopes/WorkspaceScope.php) filtra por `auth()->user()->workspace_id`. Super-admin (sin workspace_id) ve todo.
- Webhooks de Meta y jobs en cola NO tienen auth() — deben resolver el workspace explícitamente vía el `external_id` del canal (phone_number_id, ig_user_id, page_id).

## Convenciones backend

- **API tokens del SaaS** son distintos a Sanctum: modelo propio `ApiToken` con scopes (`messages:write`, etc), formato `sk_live_xxx`, hash SHA256 en DB. Sanctum se usa SOLO para auth de usuarios humanos del dashboard.
- **Adapter pattern por canal**: `App\Channels\ChannelAdapter` (interface) + `WhatsAppCloudAdapter`, `InstagramAdapter`, `MessengerAdapter`. La app fuera de los adapters NUNCA mira el payload Meta.
- **DTOs con Spatie Data** para `NormalizedMessage`, payloads normalizados, etc.
- **Idempotencia obligatoria** en POSTs: `Idempotency-Key` header + store en Redis 24h.
- **Outbox pattern** para llamadas a Meta y webhooks salientes. Nunca HTTP en plena petición.
- **Encryption at-rest** de access_tokens Meta con `Crypt::encryptString`.

## Convenciones frontend

Ver [frontend/AGENTS.md](frontend/AGENTS.md). Resumen: Hugeicons obligatorio, Euclid obligatorio, cards `rounded-3xl border border-border`, botones primarios negros, sin gradientes.

## Iconos Hugeicons que NO existen (lección aprendida en rsv)

- `HandshakeIcon`, `CoinsHandIcon`, `Home01Icon`, `Building01Icon`, `ExternalLinkXxIcon` (todas variantes).
- Verificar siempre antes de usar:
  ```bash
  grep -q "\bIconName\b" frontend/node_modules/@hugeicons/core-free-icons/dist/types/index.d.ts && echo OK || echo NO
  ```
- Alternativas válidas: `Agreement02Icon` (handshake), `Coins01Icon`, `PropertyNewIcon`, `Building03Icon`, `Download01Icon` o `LinkSquare02Icon` (external link).

## Patrón RHF + Zod con `z.coerce`

RHF v7+ es estricto con tipos input/output. Siempre 3 type params:

```ts
type FormInput = z.input<typeof schema>;
type FormData = z.output<typeof schema>;
useForm<FormInput, unknown, FormData>({ resolver: zodResolver(schema) });
```

## Estado actual del proyecto

- **Fase 0 (setup)**: en marcha.
- Repos creados: backend Laravel 13, frontend Next 16, Docker compose, fonts, theme, layout shell, /api/health, modelos Workspace/User, trait + scope multi-tenant.
- **Próximo:** Fase 1 — auth Sanctum (register que crea workspace + trial 14d, login, /me).

Para detalles de fases siguientes ver [architecture.md](architecture.md) sección 20.
