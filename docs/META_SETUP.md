# Manual Meta para Sinapsa (Tech Provider)

> Sinapsa actúa como **Tech Provider** ante Meta. Tus clientes SaaS embeben tu botón "Conectar" — sus usuarios finales autorizan vía un popup que usa **tu** app Meta. NO necesitan crear apps Meta propias.

Este manual cubre los 3 canales: **WhatsApp Cloud API · Instagram DM · Facebook Messenger**.

Lo importante en una línea: vas a registrar **una sola app Meta tipo Business** que active los 3 productos, y configurarás **tres flujos Embedded Signup** distintos (uno por canal). Cada canal tiene su propia App Review.

---

## Tabla de contenidos

1. [Prerequisitos](#prerequisitos)
2. [Crear Business Manager + verificación de empresa](#1-business-manager--verificación-de-empresa)
3. [Crear la app Meta tipo Business](#2-crear-la-app-meta-tipo-business)
4. [WhatsApp Cloud API](#3-whatsapp-cloud-api)
5. [Instagram Messaging](#4-instagram-messaging)
6. [Facebook Messenger](#5-facebook-messenger)
7. [Embedded Signup (WA / IG / FB)](#6-embedded-signup)
8. [Webhooks](#7-webhooks)
9. [System User + tokens largos](#8-system-user--tokens-largos)
10. [Tech Provider role / Solution Partner](#9-tech-provider-role--solution-partner)
11. [App Review](#10-app-review)
12. [Variables a cargar en Sinapsa](#11-variables-de-entorno-en-sinapsa)
13. [Pricing y costos](#12-pricing-y-costos)
14. [Checklist de "listo para producción"](#13-checklist-de-listo-para-producción)
15. [Troubleshooting](#14-troubleshooting)

---

## Prerequisitos

| Requisito | Por qué | Donde |
|---|---|---|
| Cuenta personal de Facebook | No se puede crear app sin ella | [facebook.com](https://facebook.com) |
| **Business Manager** | Contenedor de tus assets (apps, páginas, WABAs) | [business.facebook.com](https://business.facebook.com) |
| **Verificación de empresa** | Bloqueante para pasar App Review en producción | Business Manager → Settings → Security Center |
| **Página de Facebook** | Necesaria para Messenger e Instagram (la página vincula al perfil profesional) | [facebook.com/pages/create](https://facebook.com/pages/create) |
| **Cuenta Instagram Business o Creator** | Convertir tu IG personal a Business o usar uno nuevo | App Instagram → Configuración → Cuenta → Cambiar a Business |
| **Número WhatsApp dedicado** | NO puede estar registrado en WhatsApp consumer ni Business app — móvil distinto al personal | Necesitas un SIM/eSIM con SMS o llamada para verificar |
| **Política de privacidad pública** | Bloqueante App Review | tudominio.com/privacy |
| **Términos de Servicio públicos** | Bloqueante App Review | tudominio.com/terms |
| **Dominio HTTPS para webhooks** | Meta no acepta HTTP. En dev usar `ngrok http 48000` | [ngrok.com](https://ngrok.com) |

> **Verificación de empresa**: Meta te pedirá documentos legales (registro mercantil, factura de servicios, dominio asociado). Lleva 1-3 días. **Hazlo lo primero**: el resto depende de esto.

---

## 1. Business Manager + Verificación de Empresa

### Crear Business Manager
1. [business.facebook.com](https://business.facebook.com) → "Crear cuenta".
2. Nombre del negocio: el que vayas a usar comercialmente (ej. "Sinapsa Tech S.L.").
3. Email + nombre completo.
4. **Apunta el `Business Manager ID`** (se ve en `Settings → Business Info`). Lo necesitarás luego.

### Verificación de empresa (BLOQUEANTE)
1. Business Manager → `Settings (Configuración del negocio)` → `Security Center`.
2. Click **"Iniciar verificación"**.
3. Sube:
   - Documento legal: alta autónomo / escritura constitución SL / certificado registro mercantil.
   - Documento dirección: factura luz/agua o recibo bancario reciente.
   - Verificación de dominio (DNS TXT o meta tag).
4. Espera 1-3 días. Estado en Security Center.

**Si la verificación falla**, Meta da feedback específico — corrige y reenvía. El error más común es que el nombre del documento no coincida 100% con el del Business Manager. Cambia el nombre en BM si hace falta para que matchee.

---

## 2. Crear la app Meta tipo Business

1. [developers.facebook.com](https://developers.facebook.com) → **My Apps** → **Create app**.
2. Selecciona **Business** como tipo de app.
3. Nombre: `Sinapsa` (o el que uses comercialmente).
4. Email de contacto + selecciona tu Business Manager.
5. Pulsa **Create**.

### Configurar la app
- Ve a `Settings → Basic`:
  - **App Domains**: `sinapsa.app` (tu dominio).
  - **Privacy Policy URL**: `https://sinapsa.app/privacy`.
  - **Terms of Service URL**: `https://sinapsa.app/terms`.
  - **Category**: `Business and pages`.
  - **Icon de la app** (1024x1024).
- Apunta:
  - `App ID` → será tu **`META_APP_ID`** en `.env`.
  - `App Secret` (click "Show") → será tu **`META_APP_SECRET`** en `.env`. **NUNCA en frontend ni en logs**.

---

## 3. WhatsApp Cloud API

### Activar el producto
1. En tu app Meta → `Products` (sidebar izq) → busca **WhatsApp** → **Set up**.
2. Selecciona tu Business Manager.
3. Meta te asigna automáticamente:
   - Una **WhatsApp Business Account de prueba** (WABA test).
   - Un **número de teléfono de prueba** (no real, para dev).
   - Hasta **5 destinatarios de prueba** (números reales que pueden recibir tus mensajes en sandbox).

### Añadir tu número real (cuando vayas a producción)
1. Dentro de WhatsApp → `API Setup` → click **"Add phone number"**.
2. Selecciona / crea una **WABA real**. Necesitas:
   - Display name (lo que ven los clientes — Meta lo aprueba).
   - Categoría del negocio.
   - Descripción.
3. Añade el número (formato internacional). Meta envía SMS/llamada de verificación.
4. Apunta:
   - `Phone Number ID` → identifica el canal (irá en `channels.external_id`).
   - `WhatsApp Business Account ID` (WABA ID) → necesario para gestionar plantillas y suscribir webhooks.

### Permisos que necesitarás aprobar (App Review)
- `whatsapp_business_management` — gestión de plantillas, perfil del negocio, números.
- `whatsapp_business_messaging` — enviar/recibir mensajes en nombre del cliente.
- `business_management` — leer estructura del Business Manager del cliente.

---

## 4. Instagram Messaging

### Pre-requisito
Tu cuenta Instagram debe ser **Business** o **Creator** y estar **vinculada a una página de Facebook**.

1. App Instagram (móvil) → Configuración → **Cuenta** → "Cambiar a cuenta profesional" → Business.
2. En el flujo te pide vincular a una página de Facebook (la que creaste antes).

### Activar el producto en la app Meta
1. En tu app → `Products` → busca **Instagram** → **Set up** (puede aparecer como "Instagram Graph API" o "Messenger API for Instagram").
2. Hay DOS productos relacionados:
   - **Instagram Graph API** — para leer perfiles, medios, etc.
   - **Messenger API for Instagram** — para mensajería DM. **Este es el que te interesa**.
3. Conecta tu página de Facebook que tiene la cuenta IG vinculada.
4. Apunta:
   - `Instagram User ID` (IG_ID, también llamado `ig_business_account_id`) → irá en `channels.external_id` para canales IG.
   - `Page ID` de la página vinculada.

### Permisos que necesitarás aprobar
- `instagram_basic` — leer perfil de Instagram.
- `instagram_manage_messages` — enviar/recibir DM.
- `pages_show_list` — listar páginas del usuario.
- `pages_messaging` — necesario porque IG DM va a través de la Page.
- `pages_manage_metadata` — suscribir webhooks a la página.

---

## 5. Facebook Messenger

### Activar el producto
1. En tu app Meta → `Products` → busca **Messenger** → **Set up**.
2. Selecciona la página de Facebook vinculada a tu Business Manager (la que creaste en Prerequisitos).
3. **Generate Access Token** para esa página → te da un page access token.
4. Apunta:
   - `Page ID` → irá en `channels.external_id` para canales Messenger.

### Permisos que necesitarás aprobar
- `pages_messaging` — enviar/recibir mensajes desde la página.
- `pages_messaging_subscriptions` — recibir webhooks (mensaje, postback, etc).
- `pages_show_list`.
- `pages_manage_metadata`.

---

## 6. Embedded Signup

Embedded Signup es el **popup oficial de Meta** que tus clientes finales ven. Sin esto, NO hay Connect-as-a-Service — los clientes tendrían que pegar tokens a mano (lo que ya tienes en modo dev).

Hay **3 Embedded Signups distintos** que configurar — uno por canal.

### 6.A WhatsApp Embedded Signup

1. App Meta → `WhatsApp` → `Configuration` → busca **"Embedded Signup"** o **"Add Embedded Signup configuration"**.
2. **Create configuration**:
   - **Configuration name**: `Sinapsa WhatsApp Connect`.
   - **Solution ID** (si te lo pide): déjalo vacío salvo que tengas un Solution Partner ID asignado por Meta.
   - **Pre-verified phone numbers**: opcional. Si activas, los clientes pueden saltar la verificación SMS si Meta ya verificó su número.
   - **Setup type**: `Cloud API` (NO On-Premises).
3. Guarda.
4. Apunta:
   - `Configuration ID` → será tu **`META_WA_EMBEDDED_SIGNUP_CONFIG_ID`** en `.env`.

### 6.B Instagram Embedded Signup

1. App Meta → `Messenger` → `Settings` → busca **"Embedded Signup"** o **"Configure Login experience for Instagram"**.
2. Setup similar al de WA — configuration con scope IG.
3. Apunta el `Configuration ID` → **`META_IG_EMBEDDED_SIGNUP_CONFIG_ID`**.

### 6.C Messenger Embedded Signup

1. App Meta → `Messenger` → `Settings` → **"Embedded Signup for Messenger"**.
2. Configuration con scope Messenger (`pages_messaging`, etc).
3. Apunta `Configuration ID` → **`META_FB_EMBEDDED_SIGNUP_CONFIG_ID`**.

> **Importante**: en `Settings → Basic` de la app, en **"App Domains"** y **"Site URL"** debe estar listado el dominio donde sirvas la Hosted Connect Page (`https://app.sinapsa.app`). Si no, el popup falla con `URL Blocked`.

---

## 7. Webhooks

Cada canal suscribe webhooks por separado. Todos van al **mismo dominio Sinapsa**, en paths distintos.

### URLs callback de Sinapsa

| Canal | Callback URL | Verify Token |
|---|---|---|
| WhatsApp | `https://api.sinapsa.app/webhooks/meta/whatsapp` | `META_WEBHOOK_VERIFY_TOKEN` |
| Instagram | `https://api.sinapsa.app/webhooks/meta/instagram` | mismo |
| Messenger | `https://api.sinapsa.app/webhooks/meta/messenger` | mismo |

> En dev: usa `ngrok http 48000` para exponer tu Sinapsa local. Pega la URL `https://abc-123.ngrok-free.app/webhooks/meta/whatsapp` en Meta.

### Configurar webhook WA
1. App Meta → `WhatsApp` → `Configuration` → **Webhook**.
2. **Callback URL**: la URL Sinapsa.
3. **Verify Token**: el valor que pondrás en `META_WEBHOOK_VERIFY_TOKEN` del `.env` Sinapsa.
4. Pulsa **Verify and save**. Si tu Sinapsa está corriendo y el token coincide, Meta te muestra "Webhook verificado ✓". Si falla, comprueba logs.
5. **Subscribe to fields**:
   - ✅ `messages` (mensajes inbound + statuses outbound)
   - ✅ `message_template_status_update` (Meta aprueba/rechaza plantillas)
   - ✅ `account_review_update` (cambios de status del WABA)
   - ✅ `account_update` (cambios de calidad de la línea)
   - ✅ `phone_number_quality_update` (rating de calidad del número)

### Configurar webhook Instagram
1. App Meta → `Messenger` → `Webhooks` (o `Instagram → Webhooks` según versión de la consola).
2. **Object**: `Instagram`.
3. Callback URL + verify token (igual que WA).
4. Subscribe a:
   - ✅ `messages`
   - ✅ `messaging_postbacks`
   - ✅ `messaging_seen`
5. Click "Add Subscription" en cada página IG conectada.

### Configurar webhook Messenger
1. App Meta → `Messenger` → `Webhooks`.
2. **Object**: `Page`.
3. Callback URL + verify token.
4. Subscribe a:
   - ✅ `messages`
   - ✅ `messaging_postbacks`
   - ✅ `message_deliveries`
   - ✅ `message_reads`
5. **Importante**: cada página Facebook debe estar suscrita individualmente. Esto lo hace Sinapsa automáticamente al conectar el canal (`POST /{page_id}/subscribed_apps`).

### Verificación de firma (X-Hub-Signature-256)
Meta firma cada webhook con HMAC-SHA256 usando tu **App Secret**. Sinapsa lo verifica en `App\Channels\Support\MetaSignatureVerifier`. Si la firma falla → 401 + log `meta.webhook.signature_invalid`. Esto significa que `META_APP_SECRET` no coincide con el que Meta usa.

---

## 8. System User + tokens largos

Para que Sinapsa pueda llamar a Graph API en nombre del cliente final cuando este NO está conectado (mensajes async, refresh templates, etc), necesitas un **System User** en tu Business Manager.

> **NOTA importante para Embedded Signup**: cuando un cliente final completa el Embedded Signup, Meta te devuelve un `code` que canjeas por un **system user access token** del cliente. Ese token tiene 60 días de vida. Sinapsa lo cifra con `Crypt::encryptString` y lo persiste en `channels.access_token_encrypted`.

### Crear System User para Sinapsa (tu propio Business)
Útil para health-checks y operaciones internas:

1. Business Manager → `Settings` → `Users` → `System users` → **Add**.
2. Nombre: `Sinapsa Server`.
3. Role: `Admin`.
4. Después de crear → **Generate New Token**:
   - Selecciona la app Sinapsa.
   - Permisos: `whatsapp_business_management`, `whatsapp_business_messaging`, `business_management`, `pages_messaging`, `pages_show_list`, `instagram_manage_messages`.
   - Token expiration: `Never`.
5. **Copia el token** — solo se muestra una vez. Guárdalo en tu vault.

> Este token lo usas SOLO para operaciones internas de Sinapsa (no para servir mensajes de clientes). Cada cliente tiene su propio token.

---

## 9. Tech Provider role / Solution Partner

Hay dos categorías relacionadas pero distintas:

### Tech Provider (más fácil)
Es un **rol** dentro del Business Manager del cliente. Cuando el cliente final hace Embedded Signup, automáticamente te concede el rol `Tech Provider` sobre su WABA. Eso te permite gestionar plantillas, leer datos, etc.

**No requiere acción adicional de tu parte** — el flujo Embedded Signup lo hace automáticamente.

### Solution Partner (más prestigioso, opcional)
Programa formal de Meta. Beneficios:
- Aparces en el [Meta Business Partner Directory](https://www.facebook.com/business/partner-directory).
- Acceso a soporte prioritario.
- Mejor pricing en algunos casos.
- Tu logo aparece en el popup de Embedded Signup.

**Requisitos**:
- Negocio establecido (>1 año).
- Verificación de empresa Meta (lo del paso 1).
- Demostrar volumen mínimo de mensajes (~10k/mes).
- Application en [partners.facebook.com](https://partners.facebook.com/manage).

**No es bloqueante para arrancar.** Empieza siendo Tech Provider de facto vía Embedded Signup. Solicita Solution Partner cuando tengas tracción.

---

## 10. App Review

App Review es el proceso por el que Meta valida que tu uso de los permisos es legítimo. **Cada permiso requiere review individual**.

### Antes de empezar
- Verificación de empresa ✅ (paso 1).
- Privacy policy + ToS público ✅.
- App icon + categoría ✅.
- Demo URL funcional (puede ser tu landing).
- **Screencast video** (1-3 min): muestra a un cliente final usando Embedded Signup → conectando su WhatsApp → enviando un mensaje desde tu app cliente.

### Permisos a solicitar (orden recomendado)

#### Bloque 1 — WhatsApp (haz primero, es lo más rápido)
- `whatsapp_business_management`
- `whatsapp_business_messaging`
- `business_management`

Tiempo: 1-3 semanas. Meta puede pedirte cambios al video.

#### Bloque 2 — Instagram (después de WA)
- `instagram_basic`
- `instagram_manage_messages`
- `pages_messaging`
- `pages_show_list`
- `pages_manage_metadata`

#### Bloque 3 — Messenger (en paralelo con IG)
- `pages_messaging`
- `pages_messaging_subscriptions`
- `pages_show_list`

### Cómo aprobar el video de screencast
- **Voz en off** explicando cada paso (en inglés es más fácil).
- Muestra:
  1. Tu app cliente (CRM/SaaS) con el botón "Conectar WhatsApp".
  2. Click → popup Embedded Signup oficial Meta aparece.
  3. Cliente loguea con su Facebook → selecciona Business → selecciona/crea WABA → introduce número → SMS verify.
  4. Popup cierra → tu app muestra "Conectado ✓".
  5. Cliente envía un mensaje de prueba desde tu app → llega al móvil de prueba.
  6. Mensaje inbound de prueba → llega a la bandeja de tu app.
- **Sin saltos**. Sin "este es un video stock".
- 1080p mínimo. 60fps si puedes.

### Errores comunes en App Review
| Razón | Solución |
|---|---|
| Privacy policy no menciona Meta data | Añade párrafo: "We process WhatsApp/Instagram/Messenger data on behalf of our customers. We do not sell..." |
| Video corto / sin contexto | Re-graba mostrando el flujo completo end-to-end |
| Permisos pedidos no usados en demo | Quita del review los que no muestres |
| App Domain no coincide | `Settings → Basic → App Domains` debe coincidir con `Site URL` y con donde sirves la Hosted Connect Page |
| `data_deletion_callback_url` ausente | Configurar en `Settings → Basic` — endpoint que recibe peticiones GDPR de Meta |

---

## 11. Variables de entorno en Sinapsa

Cuando termines toda la configuración, copia estos valores a `backend/.env` (prod):

```bash
# Meta App
META_APP_ID=1234567890                          # Settings → Basic → App ID
META_APP_SECRET=abcdef1234567890abcdef          # Settings → Basic → App Secret (NUNCA frontend)
META_GRAPH_VERSION=v22.0                        # Versión actual estable

# Webhook
META_WEBHOOK_VERIFY_TOKEN=un-string-aleatorio-largo-tu-eliges
META_PUBLIC_WEBHOOK_URL=https://api.sinapsa.app/webhooks/meta

# Embedded Signup configurations (uno por canal)
META_WA_EMBEDDED_SIGNUP_CONFIG_ID=987654321
META_IG_EMBEDDED_SIGNUP_CONFIG_ID=987654322
META_FB_EMBEDDED_SIGNUP_CONFIG_ID=987654323

# Frontend (para Hosted Connect Page)
NEXT_PUBLIC_API_URL=https://api.sinapsa.app
SINAPSA_HOSTED_URL_BASE=https://app.sinapsa.app
```

> **Nunca** exponer `META_APP_SECRET` en frontend. Solo `META_APP_ID` y los `*_CONFIG_ID` viajan al cliente vía `/api/v1/connect-sessions/{token}/info`.

---

## 12. Pricing y costos

> Meta ajusta precios cada 6 meses. Verifica en [developers.facebook.com/docs/whatsapp/pricing](https://developers.facebook.com/docs/whatsapp/pricing).

### WhatsApp Cloud API (lo más caro)

Modelo **conversation-based** desde 2024. Una "conversación" = ventana de 24h por categoría.

| Categoría | España (€/conv) | LATAM (€/conv) | EE.UU. (€/conv) |
|---|---|---|---|
| **Service** (cliente inicia) | 1000 free/mes, después gratis | 1000 free/mes, después gratis | igual |
| **Utility** (transactional) | ~0.034 | ~0.005-0.025 | ~0.011 |
| **Marketing** | ~0.063 | ~0.025-0.050 | ~0.025 |
| **Authentication** (OTP) | ~0.034 | ~0.005-0.025 | ~0.011 |

Tu modelo de **Connect-as-a-Service**: el cliente final paga a Meta directamente con SU tarjeta (Meta cobra a su WABA, no al tuyo). Tú cobras a tu cliente SaaS un **markup de servicio** o **fee fija de transporte** por encima.

### Instagram Messaging
- **Gratis dentro de la ventana 24h**.
- Fuera de ventana: requiere `tag=HUMAN_AGENT` + handler humano. Sin coste adicional, pero hay límites.

### Facebook Messenger
- **Gratis dentro de la ventana 24h**.
- Fuera: tags específicos (`CONFIRMED_EVENT_UPDATE`, `POST_PURCHASE_UPDATE`, etc) o sponsored messages (de pago).

### Solution Partner Pricing
Si te aceptan como Solution Partner, recibes:
- **Volume discounts** sobre las tarifas conversation.
- **0% markup** en algunos casos por ser partner certificado.

---

## 13. Checklist de "listo para producción"

Imprímelo y tachalo:

### Setup base (1-3 días)
- [ ] Cuenta Facebook personal
- [ ] Business Manager creado
- [ ] **Verificación de empresa aprobada** (BLOQUEANTE, hazlo primero)
- [ ] Página de Facebook
- [ ] Cuenta Instagram Business vinculada a la página
- [ ] Dominios configurados: `sinapsa.app`, `api.sinapsa.app`, `app.sinapsa.app`
- [ ] Política de privacidad pública en `sinapsa.app/privacy`
- [ ] Términos de Servicio públicos en `sinapsa.app/terms`

### App Meta (1 día)
- [ ] App tipo Business creada
- [ ] App Domains, Privacy URL, ToS URL en Settings → Basic
- [ ] App icon 1024x1024
- [ ] `META_APP_ID` apuntado
- [ ] `META_APP_SECRET` apuntado y guardado en vault (NO en frontend)
- [ ] `data_deletion_callback_url` configurada

### Productos (1-2 días)
- [ ] WhatsApp añadido + WABA test funcionando
- [ ] Número WA real añadido + verificado (cuando esté listo prod)
- [ ] Instagram + página vinculada
- [ ] Messenger + page access token

### Embedded Signup (1 hora)
- [ ] WA Embedded Signup config creado → `META_WA_EMBEDDED_SIGNUP_CONFIG_ID`
- [ ] IG Embedded Signup config creado → `META_IG_EMBEDDED_SIGNUP_CONFIG_ID`
- [ ] FB Embedded Signup config creado → `META_FB_EMBEDDED_SIGNUP_CONFIG_ID`

### Webhooks (1 hora)
- [ ] WA webhook → `https://api.sinapsa.app/webhooks/meta/whatsapp`
- [ ] WA suscrito a `messages, message_template_status_update, account_*`
- [ ] IG webhook → `https://api.sinapsa.app/webhooks/meta/instagram`
- [ ] FB webhook → `https://api.sinapsa.app/webhooks/meta/messenger`
- [ ] `META_WEBHOOK_VERIFY_TOKEN` cargado en `.env` Sinapsa
- [ ] Test inbound real funciona (envía un WA al número de prueba → llega a la bandeja)

### App Review (1-3 semanas, en paralelo)
- [ ] Screencast video grabado
- [ ] WhatsApp permisos solicitados → ⏳ pending → ✅ approved
- [ ] Instagram permisos solicitados → ⏳ pending → ✅ approved
- [ ] Messenger permisos solicitados → ⏳ pending → ✅ approved

### Sinapsa prod
- [ ] `.env` con todas las variables anteriores
- [ ] HTTPS configurado en api.sinapsa.app
- [ ] HTTPS configurado en app.sinapsa.app (Hosted Connect Page)
- [ ] Cliente SaaS de prueba probó el SDK end-to-end con un cliente final real
- [ ] Mensajes inbound + outbound funcionan
- [ ] Webhooks salientes a clientes SaaS funcionan

### Solution Partner (opcional, mes 2+)
- [ ] Aplicar a Solution Partner program
- [ ] Listing en partner directory
- [ ] Logo en popups Embedded Signup

---

## 14. Troubleshooting

### "URL Blocked" al abrir el popup Embedded Signup
- `Settings → Basic → App Domains` debe contener `sinapsa.app` y `app.sinapsa.app`.
- `Site URL` y `Mobile Site URL` también deben matchear.

### Webhook se queda en "Verifying..." forever
- `META_WEBHOOK_VERIFY_TOKEN` no coincide entre Meta console y Sinapsa `.env`.
- Sinapsa no responde 200 al `hub.challenge`.
- Tu URL no es accesible desde fuera (firewall, ngrok caducado).

### Mensaje 401 Invalid signature en logs
- `META_APP_SECRET` no coincide con el que Meta usa.
- Hay un proxy/CDN que modifica el body antes de llegar a Sinapsa (Cloudflare con compresión activa puede romper la firma — desactívala en webhook routes).

### "Number quality rating: low/medium" en WA
- Demasiados mensajes marcados como spam por usuarios.
- Mensajes fuera de ventana 24h sin opt-in.
- Solución: enviar SOLO plantillas marketing con opt-in claro y reducir frecuencia.

### Token Meta caducó (error 190)
- Los system user tokens de Embedded Signup duran 60 días. Sinapsa los refresca con `RefreshMetaTokensJob`.
- Si el cliente revoca permisos manualmente, recibes 401 — marca canal como `error` y notifica.

### Cliente Embedded Signup completa pero su WABA no aparece
- Puede que falte aceptar términos en el Business Manager.
- Verifica logs: el callback puede estar devolviendo `code` pero el exchange falla con `Insufficient permissions`.
- Solución: revisar permisos del system user que generas en `MetaEmbeddedSignupService::exchangeForSystemToken`.

### App Review rechazado: "Screencast doesn't show real usage"
- Re-graba mostrando un cliente FINAL real (no tú clonando) usando el flujo en una app cliente real.
- Habla en off explicando.
- Muestra el botón Sinapsa.connect dentro de UN CRM real (puede ser tu propio CRM si lo tienes desplegado).

---

## Recursos oficiales

- [WhatsApp Business Platform docs](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [Embedded Signup WA](https://developers.facebook.com/docs/whatsapp/embedded-signup)
- [Messenger Platform docs](https://developers.facebook.com/docs/messenger-platform)
- [Instagram Messaging docs](https://developers.facebook.com/docs/messenger-platform/instagram)
- [App Review guide](https://developers.facebook.com/docs/app-review)
- [Pricing WA Cloud](https://developers.facebook.com/docs/whatsapp/pricing)
- [Meta Business Partners](https://www.facebook.com/business/partner-directory)

---

## Resumen ejecutivo en 7 puntos

1. **Crea Business Manager y verifica empresa primero** — bloqueante de TODO.
2. **Una sola app Meta tipo Business**, con WA + IG + Messenger productos activados.
3. **3 Embedded Signup configurations** distintas (una por canal) → 3 IDs.
4. **3 webhooks** distintos hacia Sinapsa, cada uno con su lista de fields.
5. **App Review por separado** para cada bloque de permisos (WA / IG / FB).
6. **Tech Provider role automático** vía Embedded Signup. Solution Partner es premium opcional.
7. **Política privacidad + ToS + dominio HTTPS + verificación empresa** son los 4 jinetes que rechazan más App Reviews.

> Tiempo total realista desde cero hasta Connect-as-a-Service producción: **4-8 semanas**. La mayor parte es esperar verificación de empresa (1-3d) + 3 App Reviews (1-3 sem cada uno, pueden ir en paralelo).
