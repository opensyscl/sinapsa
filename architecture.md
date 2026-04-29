# Sinapsa — Arquitectura y plan de producto

> **Codename / repo:** `one-rrss`
> **Producto público:** Sinapsa
> **Tagline:** *"El sistema nervioso central de tus conversaciones."*
> **Dominio sugerido:** `sinapsa.app` (workspace en `{slug}.sinapsa.app`)
> **Ruta:** `/home/jos/josbert.dev/one-rrss/`

Documento vivo. El brief original se mantiene en [plan.md](plan.md).

---

## 1. Resumen del producto

**Sinapsa es una pasarela omnicanal multi-empresa** que centraliza WhatsApp Cloud API, Instagram DM y Facebook Messenger en un único hub, expone una **API REST y webhooks unificados** para que cualquier sistema externo (CRM, bot, ERP, n8n) envíe y reciba mensajes con un solo contrato, y ofrece una **bandeja de conversaciones** lista para usar.

Una empresa conecta su Meta Business → Sinapsa unifica todos sus canales bajo un workspace → recibe webhooks normalizados de cualquier canal, envía con la misma firma, y se integra con cualquier herramienta vía API/webhook/n8n.

---

## 2. Problema

| Problema real | A quién | Impacto hoy |
|---|---|---|
| Cada API de Meta es distinta (Cloud API ≠ Graph IG ≠ Send API Messenger). 3-5 semanas dev por canal | PYMEs, CRMs pequeños, agencias | No integran o pagan Twilio (caro) / 360Dialog (sólo WA) |
| WhatsApp Cloud exige plantillas aprobadas, ventana 24h, opt-in, gestión tokens | Cualquiera con WA Business | Errores constantes, bloqueos, rechazos |
| Tokens Meta expiran (60d long-lived), webhooks fallan silenciosamente | Todos | Conversaciones perdidas sin que nadie se entere |
| No hay punto único para recibir mensajes de los 3 canales | CRMs nicho, asistentes IA, bots | Cada uno construye su propio ETL |
| Empresas multi-marca mezclan números | Holdings, agencias, franquicias | Caos operativo, rotura SLA |
| Compliance Meta cambia | Todos | Quien no se actualiza, queda fuera |

---

## 3. Propuesta de valor

> **"Conecta WhatsApp, Instagram y Messenger en 5 minutos. Envía con UNA sola API. Recibe un único webhook normalizado. Sin tocar Meta Graph nunca más."**

Tres niveles de valor:

1. **Developers / SaaS / CRMs** → API y webhooks unificados. 3 integraciones → 1.
2. **Empresas operativas** → bandeja unificada multiusuario + plantillas + automatizaciones. Sin necesidad de un CRM grande.
3. **Integradores / agencias / no-code** → conector a n8n, Make, Zapier, Kommo, HubSpot.

**Diferenciación:**
- vs **Twilio**: 70% más barato, especializado Meta, multi-tenant nativo, soporte español.
- vs **360Dialog / Wassenger**: cubre IG y FB Messenger, no solo WA.
- vs **Chatwoot**: Sinapsa es **infra como API**. Otros SaaS construyen encima.

---

## 4. Usuarios objetivo

**Tier 1 (early adopter, paga):** equipos producto/dev de CRMs nicho (real estate, salud, educación), agencias de IA/chatbots, SaaS verticales que quieren añadir mensajería.

**Tier 2 (volumen):** PYMEs/agencias inmobiliarias multi-marca, eCommerce con WA+IG, franquicias.

**Tier 3 (distribución):** consultores n8n/Make/Zapier, partners Kommo/HubSpot/Freshdesk.

**Buyer persona ancla:** CTO de un CRM vertical de 10-50 personas, lleva 6 meses peleándose con Meta Graph, pierde mensajes, soporte le quema 30% del equipo.

---

## 5. Casos de uso

1. CRM vertical conecta Sinapsa → su tenant pasa a recibir/enviar WA+IG+FB con una API.
2. Agencia inmobiliaria multi-marca → 4 números WABA, bandeja unificada, ruteo por origen.
3. eCommerce con bot → pedidos pendientes disparan plantillas WA aprobadas vía API.
4. Despacho legal/médico → recibe IG DM y WA en una bandeja con asignación + SLA.
5. Integrador n8n → flujo `Lead HubSpot → plantilla WA → ticket Freshdesk` con 3 nodos.
6. Plataforma de IA → consume webhook entrante, llama modelo, responde via `POST /messages`.

---

## 6. MVP (8 semanas, 1 BE + 1 FE + PM)

| Capa | Sí MVP | No MVP |
|---|---|---|
| Canales | **WhatsApp Cloud API** completo | IG y FB Messenger |
| Conexión Meta | **Embedded Signup** WA (one-click) | OAuth manual, multi-WABA |
| Recibir | Webhook Meta → webhook normalizado out | Reglas routing, asignación auto |
| Enviar | Texto, imagen, plantillas | Listas, botones, flows |
| Modelo | Workspace, Channel, Conversation, Message, Contact | Etiquetas, equipos, SLA |
| API | `/messages`, `/conversations`, `/contacts`, `/webhooks` | Bulk, scheduling |
| Bandeja UI | Lista + thread + envío básico | Notas, auto-asignación, IA |
| Tokens | Encrypt at rest + auto-refresh long-lived | Multi-cuenta por workspace |
| Multi-tenant | `workspace_id` scope global + subdominios | RBAC granular |
| Billing | Plan único + límite mensajes/mes | Pricing por canal/categoría |
| Webhooks out | HMAC, retry exponencial, DLQ | UI firma, replays manuales |
| Compliance | Opt-in, retención 90d, audit | DSAR auto, residencia EU/US |

**Definición de hecho:**
- 1 workspace conecta WA Cloud en <5 min.
- Mensaje WA real → webhook al cliente normalizado <2s.
- Plantilla via `POST /messages` → móvil <3s.
- Bandeja con thread cronológico, leído, envío.
- 99% entregados con retry si Meta 5xx.

---

## 7. Módulos

```
1. Identity & Tenancy        Workspaces, users, RBAC
2. Channel Connectors        WA Cloud, IG, FB Messenger
3. Webhook Ingestion         Verifica firma Meta, encola
4. Message Normalizer        Adapter por canal → modelo
5. Conversation Engine       Threads, contactos, estado
6. Outbound Dispatcher       Cola, rate-limit, plantillas
7. Public API                REST + tokens API
8. Outbound Webhooks         Hacia clientes, firma, retry
9. Inbox UI                  Bandeja operativa
10. Templates & Catalog      Sync plantillas WA
11. Contacts & CDP-lite      Identidad cross-canal
12. Billing & Usage Metering Mensajes facturables
13. Audit & Compliance       Logs, retención, opt-ins
14. Integrations Marketplace n8n, Kommo, HubSpot, etc.
15. Admin Console            Panel super-admin
16. Observability            Métricas, alertas, traces
```

---

## 8. Arquitectura técnica

**Estilo:** monolito modular Laravel + frontend Next.js separado + workers Horizon. **No microservicios** hasta que duela (≥50k msg/día por workspace top).

```
Meta Cloud API ──webhook──▶  Nginx (TLS)
IG Graph API   ──webhook──▶    │
FB Send API    ──webhook──▶    ▼
                           Laravel API (PHP-FPM)
                           ├─ /webhooks/meta/*
                           ├─ /api/v1/*
                           ├─ /webhooks/outbound/*
                           └─ /admin
                                │
        ┌──────────┬────────────┼──────────────┬─────────────┐
        ▼          ▼            ▼              ▼             ▼
   PostgreSQL   Redis 7    Horizon Workers   Meilisearch   Object Storage (R2/S3)
   (canónico)  (cache,     (inbound,         (search       (media WA/IG)
              queues,     outbound,         contactos
              sessions)   webhooks-out,     + msg)
                          retry-DLQ,
                          token-refresh)
```

**Decisiones clave:**

1. **Multi-tenancy single-DB con `workspace_id`** (idéntico patrón rsv: `BelongsToWorkspace` trait + `WorkspaceScope` global). Subdominios `{slug}.sinapsa.app`. Super-admin sin workspace ve todo.
2. **Adapter pattern por canal** → `WhatsAppCloudAdapter`, `InstagramAdapter`, `MessengerAdapter` implementan `ChannelAdapter` (`receive(payload): NormalizedMessage`, `send(NormalizedMessage): array`). Añadir Telegram = nuevo adapter, cero cambios al resto.
3. **Modelo normalizado canónico**. Fuera de adapters, la app **nunca** mira el payload Meta.
4. **Colas separadas** (`inbound`, `outbound`, `webhooks-out`, `media-download`, `token-refresh`) con sus `tries`, `backoff`, `timeout`.
5. **Idempotencia obligatoria** en ambos sentidos: Meta reenvía, clientes hacen retry. `external_id` único + `Idempotency-Key`.
6. **Outbox pattern** para webhooks salientes: persistir → worker entrega → retry exponencial → DLQ. Nunca HTTP en plena petición.
7. **Encriptación at-rest** de access_tokens Meta con `Crypt::encryptString` + KMS opcional en prod.

---

## 9. Stack

### Frontend
- **Next.js 16.2** + React 19 + TS 5.9 (App Router).
- **Tailwind CSS 4** con cards `rounded-3xl border border-border` (memoria activa).
- **TanStack Query 5** + **Zustand 5** + **RHF 7 + Zod 4**.
- **`@hugeicons/react` + `@hugeicons/core-free-icons`** (obligatorio).
- Tipografía **Euclid Circular B** (woff2 en `/frontend/public/fonts/` y `/fonts/`).
- **Laravel Reverb** (WebSocket) para realtime en bandeja.
- Recharts 3 para métricas.

### Backend
- **Laravel 13.6 + PHP 8.3**.
- **Sanctum** para auth dashboard humano.
- **Modelo `ApiToken` propio** para clientes externos del SaaS, con scopes (`messages:write`, etc), prefijo `sk_live_xxxx`.
- **Horizon** (workers + retry + dashboard).
- **Spatie**: `permission`, `activitylog`, `medialibrary`, `data` (DTOs).
- **Reverb** o Soketi para realtime.
- **Pest** para testing.
- **Guzzle** para llamadas Meta.

### Base de datos
- **PostgreSQL 16** (sin PostGIS).
- **Particionado nativo por mes** en `messages` y `webhook_deliveries` (desde día 1).
- `JSONB` para `raw_payload` y `metadata`.

### Cola
- **Redis 7 + Horizon** suficiente hasta ~100k msg/día.
- Después: AWS SQS o RabbitMQ para outbound, mantener Redis para resto.

### Webhooks
- **Inbound:** verifica `X-Hub-Signature-256` (HMAC SHA256 con app secret). Responde 200 inmediato y encola.
- **Outbound:** modelo `WebhookEndpoint` por workspace, firma `X-Sinapsa-Signature`. Reintentos: 1m, 5m, 30m, 2h, 12h, 24h. Tras DLQ: endpoint marcado `failing` + email admin.

### Auth
- **Dashboard humano:** Sanctum + 2FA TOTP opcional.
- **API SaaS externo:** `ApiToken` con scopes, hash SHA256 en DB, plaintext solo en creación.
- **Embedded Signup Meta:** OAuth code → user token → system user token (60d).

### Infra
- **Local:** Docker Compose con puertos altos.
- **Prod:** AWS Frankfurt (RGPD) o Hetzner Cloud (más barato, EU).
  - 2× backend ECS/VPS detrás de ALB/Caddy.
  - 2× workers Horizon separados.
  - Postgres RDS Multi-AZ / Hetzner managed.
  - Redis ElastiCache / Upstash.
  - **Cloudflare R2** (egress gratis, clave para media WA).
  - Cloudflare CDN/WAF.
- **Observabilidad:** Sentry + Grafana Cloud / Better Stack + Pingdom.
- **CI/CD:** GitHub Actions → tests → staging → manual approve → prod.

### Puertos Docker locales (evitando colisiones host, todos <65535)
| Servicio | Host | Container |
|---|---|---|
| Postgres | 45432 | 5432 |
| Redis | 46379 | 6379 |
| Mailpit UI | 48025 | 8025 |
| Mailpit SMTP | 41025 | 1025 |
| Reverb (WS) | 48080 | 8080 |
| Nginx (API) | 48000 | 80 |
| Frontend dev | 3002 | 3000 |
| Ngrok webhooks dev | dinámico | — |

---

## 10. Modelo de datos inicial

```sql
-- IDENTITY & TENANCY
workspaces (id, slug UNIQUE, name, status, plan_code,
            meta_business_id, retention_days DEFAULT 90, ...)
users (id, workspace_id NULL, email UNIQUE, password, name, role, ...)
memberships (id, workspace_id, user_id, role)
api_tokens (id, workspace_id, name, token_hash, prefix, scopes JSONB,
            last_used_at, expires_at, created_by_user_id)

-- CHANNELS
channels (id, workspace_id, type ENUM('whatsapp','instagram','messenger'),
          display_name, external_id, meta_business_id, status,
          access_token_encrypted, refresh_token_encrypted,
          token_expires_at, webhook_subscribed_at, config JSONB, ...)

-- CONTACTS
contacts (id, workspace_id, name, phone, email, avatar_url,
          identifiers JSONB, attributes JSONB, opt_ins JSONB,
          first_seen_at, last_seen_at)

-- CONVERSATIONS
conversations (id, workspace_id, channel_id, contact_id,
               external_thread_id, status, assigned_to_user_id,
               last_message_at, unread_count, metadata JSONB, ...)
-- INDEX UNIQUE (workspace_id, channel_id, external_thread_id)

-- MESSAGES (particionado por mes)
messages (id BIGSERIAL, workspace_id, conversation_id, channel_id, contact_id,
          direction ENUM('inbound','outbound'),
          status ENUM('queued','sent','delivered','read','failed'),
          type ENUM('text','image','audio','video','document','template',
                    'interactive','reaction','location','sticker'),
          external_id, client_idempotency_key,
          body TEXT, media_url, media_mime,
          template_name, template_payload JSONB, raw_payload JSONB,
          error_code, error_message,
          sent_at, delivered_at, read_at, failed_at, created_at
) PARTITION BY RANGE (created_at);

-- TEMPLATES WA
wa_templates (id, workspace_id, channel_id, name, language, category,
              status, components JSONB, meta_template_id, last_synced_at)

-- WEBHOOKS SALIENTES
webhook_endpoints (id, workspace_id, url, secret_encrypted,
                   events JSONB, status, last_success_at,
                   last_failure_at, consecutive_failures)
webhook_deliveries (id BIGSERIAL, workspace_id, endpoint_id, event_type,
                    payload JSONB, attempt INT, status,
                    response_status, response_body,
                    next_attempt_at, delivered_at, created_at
) PARTITION BY RANGE (created_at);

-- AUDIT
audit_logs (id, workspace_id, actor_user_id, actor_token_id, action,
            target_type, target_id, diff JSONB, ip, ua, created_at)

-- BILLING
plans (code, name, monthly_price, included_messages, overage_unit_price, features JSONB)
usage_meters (workspace_id, period_yyyy_mm, channel_type, category, count)
```

---

## 11. Conectar cuenta Meta (WhatsApp Cloud, Embedded Signup)

```
1. Usuario en /channels → click "Conectar WhatsApp"
2. Frontend abre popup Facebook Login con scope:
     business_management, whatsapp_business_management, whatsapp_business_messaging
   y config_id de Embedded Signup
3. Usuario selecciona/crea Business Manager + WABA + número
4. Meta callback → frontend recibe { code, phone_number_id, waba_id }
5. POST /api/v1/channels/whatsapp/connect { code, phone_number_id, waba_id }
6. Backend:
   a. Exchange code → user access token
   b. Exchange → system user token (60d)
   c. POST /{phone_number_id}/register
   d. POST /{waba_id}/subscribed_apps
   e. Cifra y guarda token
   f. Sync inicial plantillas
   g. channel.status = 'connected'
7. RefreshMetaTokensJob diario refresca a los 50d
```

**Errores típicos:** número no verificado, webhook fail, token revoke (marcar `error`, notificar workspace).

---

## 12. Recibir mensajes

```
Usuario WA envía "hola"
        ↓
Meta POST https://api.sinapsa.app/webhooks/meta/whatsapp
   X-Hub-Signature-256: sha256=...
        ↓
WebhookController:
  1. Verifica firma HMAC con APP_SECRET → 401 si falla
  2. Persiste raw en webhook_inbound_log (dedupe key)
  3. RESPONDE 200 INMEDIATO (Meta timeout 5s)
  4. Dispatch ProcessIncomingMetaWebhook a cola 'inbound'
        ↓
Worker:
  5. Resuelve channel via phone_number_id → workspace_id
  6. WhatsAppCloudAdapter->receive($payload) → NormalizedMessage[]
  7. Por cada mensaje:
     a. Upsert contact (phone + workspace)
     b. Upsert conversation
     c. Insert message (idempotente por external_id)
     d. Si media → encola DownloadMediaJob (R2)
     e. Update conversation.last_message_at, unread_count++
  8. Broadcast Reverb → "workspace.{id}.inbox" "MessageReceived"
  9. Encola DispatchOutboundWebhooks por endpoint suscrito
        ↓
DeliverWebhookJob:
 10. POST endpoint cliente con X-Sinapsa-Signature
 11. tries=6, backoff=[60,300,1800,7200,43200,86400]
 12. 6 fails → DLQ + endpoint failing
```

**Payload normalizado entrante (igual para los 3 canales):**

```json
{
  "id": "evt_01HK...",
  "type": "message.received",
  "workspace_id": "ws_acme",
  "occurred_at": "2026-04-27T10:21:43Z",
  "data": {
    "channel": { "id": "ch_xx", "type": "whatsapp", "display_name": "..." },
    "contact": { "id": "ct_yy", "name": "Marta", "phone": "+34...",
                 "identifiers": {"wa":"34..."} },
    "conversation": { "id": "cv_zz", "external_thread_id": "..." },
    "message": {
      "id": "msg_aa", "external_id": "wamid.HBg...",
      "direction": "inbound", "type": "text",
      "body": "Hola, info del piso de Russafa",
      "media": null, "timestamp": "2026-04-27T10:21:42Z"
    }
  }
}
```

---

## 13. Enviar mensajes

**Cliente externo:**
```http
POST https://api.sinapsa.app/api/v1/messages
Authorization: Bearer sk_live_abc123...
Idempotency-Key: order-12345-welcome
Content-Type: application/json

{
  "channel_id": "ch_xx",
  "to": { "phone": "+34666123456" },
  "type": "template",
  "template": {
    "name": "bienvenida_arrendatario", "language": "es",
    "components": [
      { "type": "body", "parameters": [{ "type": "text", "text": "Marta" }] }
    ]
  }
}
```

**Backend:**
```
1. Auth middleware valida ApiToken + scope messages:write
2. Rate limit por workspace+token (e.g. 80 req/s WA Cloud)
3. Resuelve/crea contact, conversation
4. Idempotency-Key existe en últimas 24h → response cacheada
5. INSERT message status='queued'
6. Responde 202 inmediato { id, status: 'queued' }
7. Encola SendOutboundMessage en 'outbound'
        ↓
Worker:
8. Adapter->send(NormalizedMessage):
   - Mapea a payload Graph
   - POST graph.facebook.com/v22.0/{phone_number_id}/messages
   - Maneja:
     * 400 invalid template → message.failed
     * 401 token expired → channel.error + refresh
     * 429 rate limit → retry backoff
     * 5xx → retry exponencial
9. Update message.status='sent', sent_at=now()
10. Broadcast Reverb a inbox
11. Webhooks delivered/read llegan vía /webhooks/meta y actualizan messages.status
```

**Reglas WA Cloud server-side (obligatorio, no confiar en cliente):**
- Último mensaje contacto >24h → solo plantillas. Si no, 422 `outside_24h_window`.
- Plantilla `status=APPROVED` o 422 `template_not_approved`.
- Marketing requiere `opt_ins.wa_marketing=true` o 422 `missing_optin`.
- Validar phone E.164.

---

## 14. Tokens, permisos y seguridad

### Tokens Meta
- **Encrypt at-rest:** `Crypt::encryptString` con APP_KEY rotable (en prod: KMS / Vault).
- **Refresh proactivo:** job diario por canales con `token_expires_at < now+10d`.
- **Detección revoke:** `GET /me` por canal cada hora. Error 190 → `channel.status=error`.
- **Nunca loguees el token.** Sanitize `Authorization`/`access_token` en logger middleware.

### API Tokens del SaaS
- Formato `sk_live_<32 base62>` / `sk_test_<...>`.
- **Hash SHA256** en DB. Plaintext solo al crear.
- Scopes: `messages:read|write`, `conversations:read|write`, `contacts:*`, `channels:read`, `templates:read`, `webhooks:*`.
- `last_used_at`, expiry opcional 90d.
- Revoke con un click → invalidar inmediato (cache TTL 30s máx).

### Webhooks salientes
- Cada endpoint con `secret`. Header `X-Sinapsa-Signature: t=<unix>,v1=<hmac>`.
- TTL timestamp 5min anti-replay.
- IPs salientes fijas (clientes whitelist).

### Webhook entrante Meta
- Verifica `X-Hub-Signature-256` siempre. Falla → 401 + log + alarma si frecuencia alta.
- Modo verify (`hub.mode=subscribe`) responde el `hub.challenge`.

### Aislamiento multi-tenant
- `WorkspaceScope` global en TODOS los modelos.
- Tests: workspace A NO puede leer message de B aunque adivine ID.
- Postgres RLS opcional como cinturón extra en prod.

### RGPD
- `DELETE /api/v1/contacts/{id}` borra contact + mensajes (derecho al olvido).
- `retention_days` por workspace (default 90 — alineado Meta).
- `PruneOldMessagesJob` nocturno.
- Audit log de quién accedió a qué conversación.
- T&Cs: tú procesador, workspace responsable.

### Hardening
- HTTPS+HSTS. Rate limit IP login + token API. 2FA opcional admin. CSP estricto. Dependabot semanal.

---

## 15. API propia — principios

- REST + JSON, paginación cursor.
- Versión URL: `/api/v1/`.
- **Idempotencia obligatoria** en POSTs que crean en sistemas externos.
- **Errores tipados** estilo Stripe:
  ```json
  { "error": { "type": "invalid_request_error", "code": "missing_optin",
               "message": "...", "param": "to.contact_id", "doc_url": "..." } }
  ```
- IDs prefijados: `ws_, ch_, cv_, msg_, ct_, tpl_, wh_, tok_`.
- Timestamps ISO 8601 UTC.
- Filtros estándar: `?status=...&channel_id=...&updated_after=...`.

---

## 16. Endpoints (ejemplos)

### Auth
```
POST   /api/v1/auth/register             { workspace_name, email, password }
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/me
GET    /api/v1/workspaces/current
```

### API tokens
```
POST   /api/v1/api-tokens                { name, scopes[] }   → token (solo aquí)
GET    /api/v1/api-tokens
DELETE /api/v1/api-tokens/{id}
```

### Channels
```
GET    /api/v1/channels
POST   /api/v1/channels/whatsapp/connect { code, phone_number_id, waba_id }
POST   /api/v1/channels/instagram/connect
POST   /api/v1/channels/messenger/connect
POST   /api/v1/channels/{id}/disconnect
POST   /api/v1/channels/{id}/test-send
GET    /api/v1/channels/{id}/templates
POST   /api/v1/channels/{id}/templates/sync
```

### Mensajes (la API más usada)
```
POST   /api/v1/messages                  Idempotency-Key: ...
GET    /api/v1/messages?conversation_id=cv_xx&cursor=...
GET    /api/v1/messages/{id}
POST   /api/v1/messages/{id}/retry       (si failed)
POST   /api/v1/messages/{id}/read
```

Body discriminado por `type`: `text`, `template`, `image`, `interactive`, etc.

### Conversaciones / contactos
```
GET    /api/v1/conversations?status=open&assigned_to=u_xx
PATCH  /api/v1/conversations/{id}        { status, assigned_to_user_id }
POST   /api/v1/conversations/{id}/notes
GET    /api/v1/contacts?search=marta
POST   /api/v1/contacts
GET    /api/v1/contacts/{id}
PATCH  /api/v1/contacts/{id}
DELETE /api/v1/contacts/{id}             (RGPD)
```

### Templates WA
```
GET    /api/v1/templates?channel_id=ch_xx
POST   /api/v1/templates
DELETE /api/v1/templates/{id}
```

### Webhooks salientes
```
GET    /api/v1/webhooks
POST   /api/v1/webhooks                  { url, events:[], secret? }
PATCH  /api/v1/webhooks/{id}
DELETE /api/v1/webhooks/{id}
GET    /api/v1/webhooks/{id}/deliveries
POST   /api/v1/webhooks/{id}/deliveries/{delivery_id}/replay
POST   /api/v1/webhooks/{id}/test
```

### Insights / billing
```
GET    /api/v1/usage?period=2026-04
GET    /api/v1/billing/me
POST   /api/v1/billing/upgrade           { plan_code }
```

---

## 17. Webhooks salientes (terceros)

### Eventos
| Evento | Disparador |
|---|---|
| `message.received` | Mensaje inbound persistido |
| `message.sent` | Outbound aceptado por Meta |
| `message.delivered` | Status delivered |
| `message.read` | Status read |
| `message.failed` | Send falló |
| `conversation.opened` | Primera convo de un contacto |
| `conversation.assigned` | Asignación |
| `conversation.closed` | Cierre |
| `contact.created` | Nuevo contacto |
| `contact.updated` | Update perfil |
| `template.status_updated` | Meta aprobó/rechazó |
| `channel.disconnected` | Token revoked / desconexión |
| `channel.error` | Falla persistente |

### Entrega
- POST JSON al `webhook_endpoint.url`.
- Headers:
  ```
  Content-Type: application/json
  User-Agent: Sinapsa-Webhooks/1.0
  X-Sinapsa-Signature: t=1714210800,v1=<hmac_sha256_hex>
  X-Sinapsa-Event: message.received
  X-Sinapsa-Delivery: wd_01HK...
  X-Sinapsa-Workspace: ws_acme
  ```
- Reintentos 1m → 5m → 30m → 2h → 12h → 24h. Tras 6 → `failing` + email.
- Auto-pausa tras 100 fallos.
- **Replay manual** desde dashboard (30 días).

### Sin SDKs día 1
Solo snippets verificación: Node (Express middleware), PHP (Laravel middleware), Python (FastAPI dependency). + endpoint `POST /webhooks/{id}/test` para evento dummy.

---

## 18. Panel administrativo

### A) Dashboard del workspace
- **Login / Registro** (signup auto-crea workspace + trial 14d).
- **Onboarding wizard:** conecta canal → testea → crea API token → suscribe webhook.
- **Inbox** (`/inbox`): lista lateral + thread + composer + panel contacto, realtime Reverb.
- **Channels** (`/canales`): conectados, status, botón Conectar.
- **Contacts** (`/contactos`): tabla buscable, timeline cross-canal.
- **Templates** (`/plantillas`): tabla con status Meta, sync, builder.
- **Developers** (`/desarrolladores`): tabs API tokens, Webhooks, Logs (7d).
- **Members** (`/equipo`): roles admin/agent/viewer.
- **Settings** (`/ajustes`): general, billing, compliance.
- **Insights** (`/insights`): volumen, response rate, top plantillas, costo Meta estimado.

### B) Console super-admin Sinapsa (`/admin`)
Solo super-admin (sin workspace_id):
- Lista workspaces, MRR, uso, signals.
- Suplantación con auditoría (login as).
- Pause/unpause workspace.
- Health canales globales.
- Métricas sistema (jobs, latencia webhooks, delivery rate).
- Planes y feature flags.

### C) Público / docs
- Landing `sinapsa.app`.
- `docs.sinapsa.app` (Mintlify / Nextra).
- `status.sinapsa.app`.

---

## 19. Riesgos

### Técnicos
| Riesgo | Mitigación |
|---|---|
| Meta cambia Graph API | Adapter aislado + tests contrato + monitorear changelog semanal |
| Webhook timeout Meta (5s) | 200 inmediato + cola; alarma si processing p99 >2s |
| Pérdida mensajes | Outbox + DLQ + raw payload guardado siempre antes de procesar |
| Token revoke silente | Job hourly `GET /me` por canal |
| Rate limits Meta | Token bucket por canal + cola throttle + visibilidad UI |
| Crecimiento `messages` | Particionado mensual desde día 1, archive S3 a 6m |
| Saturación Reverb | Límites por workspace, fallback polling |
| Inyección templates | Sanitización + validar contra schema aprobado |
| Latencia webhooks cliente lentos | Cola separada + timeout 10s |

### Legales
| Riesgo | Mitigación |
|---|---|
| **WA Cloud requiere App Review** Meta para producción | Empezar dev mode, App Review en mes 2-3 (1-3 sem) |
| **Embedded Signup requiere Tech Provider verificado** | Registrar Sinapsa antes del Beta público |
| RGPD datos personales | DPA disponible; residencia EU; retención configurable; DSAR endpoint |
| Retención WA 90d | Hard-limit salvo opt-in workspace |
| Spam/abuse | Rate limits, detección patrones, freeze + alert |
| Marketing sin opt-in | Enforcement server-side |
| Almacenar tokens Meta hackeable | KMS + segregación + rotation + bug bounty |
| Datos menores | T&Cs <16 años prohibido |

### Negocio
- Dependencia 100% Meta — diversificar a Telegram/iMessage cuando sólido.
- Soporte 24/7 esperado — empezar SLA 9-18 CET, ser claro.

---

## 20. Roadmap por fases (asumiendo 1 BE + 1 FE + PM)

### Fase 0 — Setup (1 sem)
- Crear `/home/jos/josbert.dev/one-rrss/` con estructura espejo de rsv.
- `composer create-project laravel/laravel backend "13.*"`.
- `pnpm create next-app frontend --ts --tailwind --app --eslint --src-dir`.
- Docker Compose puertos altos.
- CLAUDE.md, AGENTS.md frontend.
- Hugeicons + Euclid Circular B copiados.
- Tailwind 4 con cards `rounded-3xl border border-border` por defecto.
- Sentry + GitHub Actions (lint+test).
- App Meta Developers en dev mode.

**Done:** `docker compose up -d` levanta todo, `/api/health` responde, `/login` se ve.

### Fase 1 — Identity & multi-tenancy (1-2 sem)
- `Workspace`, `User`, `Membership`, `BelongsToWorkspace`, `WorkspaceScope`.
- Sanctum + register (auto-crea workspace + trial 14d).
- `/registro`, `/login`, `/dashboard` shell.
- Subdominios `{slug}.sinapsa.app`.
- Roles: owner/admin/agent/viewer.
- Audit log activo.

**Done:** dos workspaces, aislamiento testeado.

### Fase 2 — Modelo + Adapter WA Cloud (2-3 sem)
- Migraciones `channels/contacts/conversations/messages` (partitioned), `wa_templates`.
- `ChannelAdapter` interface + `WhatsAppCloudAdapter`.
- `NormalizedMessage` DTO (Spatie Data).
- Webhook `POST /webhooks/meta/whatsapp` con verificación firma.
- `ProcessIncomingMetaWebhook`, `SendOutboundMessage`.
- Encryption tokens.
- Tests E2E con payloads reales (`tests/Fixtures/meta/`).

**Done:** canal WA dev recibe → persiste → ves en `/inbox`. Envías template → móvil.

### Fase 3 — Embedded Signup WA (1-2 sem)
- `POST /api/v1/channels/whatsapp/connect`.
- Frontend popup Embedded Signup.
- Token exchange code → user → system user.
- Auto registro número + suscripción webhooks.
- Sync inicial plantillas.
- `RefreshMetaTokensJob` diario.
- UI status canal + reconectar.

**Done:** cliente conecta WA real <5min.

### Fase 4 — Bandeja UI mínima (2 sem)
- `/inbox` lista + thread + composer.
- Realtime Reverb.
- Marca leído, asignación, cierre.
- Búsqueda contacto.
- Adjuntos R2.

**Done:** dos agentes en vivo en bandeja.

### Fase 5 — API Pública v1 + API tokens (2 sem)
- Modelo `ApiToken` + middleware + scopes.
- `/api/v1/messages` + GET cursor.
- `/api/v1/conversations`, `/contacts`, `/channels`, `/templates`.
- Idempotency-Key store (Redis 24h).
- Rate limit por token.
- Errores tipados Stripe-like.
- `/desarrolladores` UI tokens.

**Done:** Postman collection. Cliente externo envía con `sk_live_...`.

### Fase 6 — Webhooks salientes + Outbox (1-2 sem)
- `WebhookEndpoint`, `WebhookDelivery` (partitioned).
- `DeliverWebhookJob` retry exponencial + DLQ.
- Firma HMAC.
- UI webhooks: crear, eventos, secret, deliveries, replay, test.
- Snippets verificación docs.

**Done:** cliente recibe `message.received` <2s. Replays ok.

### Fase 7 — Templates WA managed (1-2 sem)
- Sync bidireccional Meta.
- UI `/plantillas`: lista, status, builder, editor variables `{{1}}`.
- Webhook Meta `template.status_updated`.
- Validación opt-in marketing.

**Done:** crear plantilla → Meta aprueba → enviarla por API.

### Fase 8 — Instagram + FB Messenger (2-3 sem)
- `InstagramAdapter`, `MessengerAdapter` con misma interface.
- Embedded Signup variantes (`pages_messaging`, `instagram_manage_messages`).
- Webhook ingestion `/webhooks/meta/instagram`, `/messenger`.
- Reglas: ventana 7d Messenger, 24h IG, etiquetas mensaje.
- Tests payloads reales.

**Done:** 3 canales en una bandeja. API igual `POST /messages` con `channel_id`.

### Fase 9 — Billing + Plans + Usage metering (1-2 sem)
- Replicar planes rsv adaptado:
  - **Starter** trial 14d / 500 msg / 1 canal.
  - **Growth** 49€/mes / 10k msg / 3 canales / webhooks.
  - **Scale** 199€/mes / 100k msg / canales ilimitados / SLA.
  - **Enterprise** custom.
- `usage_meters` actualizado en cada send + inbound.
- `/planes`, `UpgradeDialog`, banner trial.
- Stripe en Fase 11.

### Fase 10 — Stripe + facturación (1-2 sem)
- Stripe Customer + Subscription por workspace.
- Webhooks Stripe.
- Auto-suspend si past_due >7d.
- Facturas descargables.

### Fase 11 — Integraciones marketplace v1 (2-3 sem)
- **n8n nodes** oficial (Trigger + Action) en npm.
- **Zapier app**.
- Conector **Kommo** (botón en Kommo, mapea contactos↔leads).
- Conector **HubSpot** (timeline events).

### Fase 12 — Insights, observabilidad (1-2 sem)
- `/insights` gráficos volumen, delivery rate, plantillas top, ventana 24h.
- Health canal alertas.
- Status page público.
- Dashboard super-admin.

### Fase 13 — Hardening pre-GA (2 sem)
- App Review Meta.
- Pen-test externo (~3-5k€).
- Tech Provider Meta.
- Backups + restore drills + runbooks.
- Política privacidad, T&C, DPA.
- Rate limits anti-abuse.

### Fase 14 — Beta privado → GA
- 5-10 clientes beta -50% precio 2 meses.
- Lanzamiento LATAM/España.
- Programa de partners.

### Fase 15+ (post-GA)
Bot builder visual, AI assist, Telegram/iMessage, Voice WA, marketplace plantillas pre-aprobadas, multi-WABA holdings.

---

## 21. Qué primero / qué después

**YA (sem 1-8):** Fases 0-5. Foco brutal en **WA Cloud + API + bandeja básica**. 1 cliente real beta a las 8 sem.

**Después (sem 9-16):** Fases 6-9. Webhooks salientes convierten Sinapsa de "bandeja barata" → "infra". Templates managed y multi-canal triplican TAM. Billing real solo con 5 clientes manuales.

**No tocar hasta 20 clientes pagando:** marketplace integrations, IA, bot builder.

**Razones del orden:**
- Embedded Signup en Fase 3, no Fase 1: primero manual con app dev (validas flujo), luego automatizas.
- API pública antes que multi-canal: el core de tu valor es **API unificada**.
- Bandeja UI antes que API pública: hace debugging trivial. Sin UI, ves mensajes solo por logs.

---

## 22. Estimación de complejidad

| Componente | Complejidad | Razón |
|---|---|---|
| Multi-tenancy single-DB | Baja | Patrón rsv, copy-paste |
| Modelo + adapters | Media-alta | Diferencias sutiles 24h WA / 7d FB / 24h IG |
| Webhooks Meta entrantes | Media | Firma + idempotencia + reintentos |
| Embedded Signup | Alta | Doc Meta confusa, edge cases |
| API Tokens | Media | Scopes + rate limit + idempotency |
| Webhooks salientes retry | Media | Outbox + cola + DLQ |
| Bandeja realtime | Media | Reverb + scroll virtual + composer multitipo |
| Templates managed | Alta | Workflow Meta lento, status webhooks, builder |
| IG/FB | Media | Variantes WA |
| Stripe | Baja-media | Estándar |
| Integraciones n8n/Zapier/Kommo | Media | Quirks |
| App Review Meta | Alta | No técnica pero **larga** (1-3 sem) |
| Pen-test + RGPD | Media | Tiempo |

**Total realista (1 BE + 1 FE):** 18-22 sem hasta GA con WA+IG+FB+integraciones básicas. Solo, x2.

**Costos primer año:**
- Infra prod (Hetzner/AWS modesto): 200-400€/mes.
- Stripe: 1.5% + 0.25€.
- Meta WA Cloud: cobras al cliente con margen.
- Sentry/BetterStack/Cloudflare: ~150€/mes.
- App Review + legal: ~3-5k€ one-shot.

---

## 23. Recomendaciones para escalar

### Técnicas
1. **Particiona desde día 1** (`messages`, `webhook_deliveries`).
2. **Outbox pattern** para todo HTTP externo. Nunca Meta o webhook cliente en plena petición.
3. **Una cola por dominio**, no una gigante. Aísla blast radius.
4. **Idempotencia obligatoria server-side**. Meta reenvía, clientes hacen retry.
5. **Telemetría por workspace + canal**.
6. **Feature flags por workspace**.
7. **Read replicas Postgres** para `/insights` y reportes.
8. **Cache agresivo** templates y canales.
9. **Health-check job hourly** por canal.
10. **Logs JSON** con `workspace_id, channel_id, external_id` siempre.

### Negocio
1. Empieza con **un canal (WA Cloud) y un idioma de docs (inglés)**.
2. **Pricing transparente y por uso**. Integradores ODIAN seats.
3. **Docs como producto** (Mintlify + curl ejemplos + Postman).
4. **No SDKs día 1**. REST + Postman basta. SDKs cuando 3 clientes los pidan.
5. **Status page desde día 1** aunque tengas 1 cliente.
6. **Webhook delivery dashboard visible** para cliente.
7. **Slack Connect** con clientes >€500/mes. Email/chat resto.
8. **Mide DELIVERED rate por workspace**. <95% → llamas tú primero.
9. **DPA y SOC2-readiness** desde mes 6 si vendes enterprise.
10. **Programa partners desde Fase 11**. Revenue share con integradores.

### Cuándo introducir microservicios
**Nunca antes de:**
- 50k mensajes/día por workspace top.
- 4 devs backend pisándose en deploys.
- Métricas claras del cuello de botella.

**Primer split lógico:**
1. Worker pool independiente para `outbound`.
2. Servicio `webhook delivery` separado.
3. **Postgres se queda monolítico mucho tiempo.** Read replicas sí, partitioning sí, microDB no.

---

## 24. Estructura del repo (espejo de real-state-valencia)

```
/home/jos/josbert.dev/one-rrss/
├── .env.example
├── .gitignore
├── README.md
├── plan.md                         # brief original
├── architecture.md                 # este documento
├── docker-compose.yml
├── docker/
│   ├── nginx/default.conf
│   ├── php/Dockerfile
│   └── postgres/
├── fonts/                          # Euclid Circular B (woff2)
├── backend/                        # Laravel 13.6
│   ├── app/
│   │   ├── Channels/               # ChannelAdapter + Whatsapp/Instagram/Messenger
│   │   ├── Http/Controllers/Api/v1/
│   │   ├── Http/Controllers/Webhooks/
│   │   ├── Jobs/
│   │   ├── Models/Concerns/BelongsToWorkspace.php
│   │   ├── Models/Scopes/WorkspaceScope.php
│   │   ├── Models/{Workspace,User,Channel,Contact,Conversation,Message,
│   │   │            Template,WebhookEndpoint,WebhookDelivery,ApiToken}.php
│   │   ├── Services/{TokenService,IdempotencyService,WebhookSigner,UsageMeter}.php
│   │   ├── Support/NormalizedMessage.php
│   │   └── ...
│   ├── routes/api.php
│   ├── routes/webhooks.php
│   ├── database/migrations/
│   └── tests/Fixtures/meta/        # payloads reales
├── frontend/                       # Next.js 16.2
│   ├── src/app/(auth)/{login,registro}/
│   ├── src/app/(app)/{dashboard,inbox,canales,contactos,plantillas,
│   │                   desarrolladores,equipo,ajustes,insights}/
│   ├── src/app/(public)/p/[slug]/
│   ├── src/components/{Inbox,Composer,ChannelCard,...}/
│   ├── src/lib/{queries,api-client,reverb}.ts
│   ├── src/store/{auth,selection}.ts
│   └── public/fonts/
└── .claude/
    └── settings.json
```
