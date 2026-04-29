# Sinapsa

> Pasarela omnicanal multi-empresa para Meta (WhatsApp Cloud API · Instagram DM · Facebook Messenger). Codename `one-rrss`.

API y webhooks unificados, bandeja propia, multi-tenant single-DB, pensada para ser consumida por CRMs, bots, SaaS verticales e integraciones (n8n, Kommo, HubSpot, Zapier).

- **Producto público:** Sinapsa
- **Codename / repo:** `one-rrss`
- **Brief:** [plan.md](plan.md)
- **Diseño completo:** [architecture.md](architecture.md)

## Stack

- **Backend:** Laravel 13.6 + PHP 8.3 + Sanctum + Horizon + Spatie + Reverb + Pest
- **DB:** PostgreSQL 16 (particionado mensual en `messages` y `webhook_deliveries`)
- **Cache/Queue:** Redis 7
- **Frontend:** Next.js 16.2 + React 19 + TS 5.9 + Tailwind 4 + Hugeicons + Euclid Circular B
- **Realtime:** Laravel Reverb (WebSocket sobre Redis)
- **Object storage:** Cloudflare R2 (prod) / disk local (dev)

## Puertos Docker locales

| Servicio | Host | Container |
|---|---|---|
| Postgres | 45432 | 5432 |
| Redis | 46379 | 6379 |
| Mailpit UI | 48025 | 8025 |
| Mailpit SMTP | 41025 | 1025 |
| Reverb (WS) | 48080 | 8080 |
| Nginx (API) | 48000 | 80 |
| Frontend dev | 3002 | 3000 |

> Puertos `4xxxx` para no chocar con servicios del host ni con el stack de `real-state-valencia` (`5xxxx`). Por debajo del límite TCP 65535.

## Quickstart

```bash
cp .env.example .env
docker compose up -d
docker exec -it one-rrss-php composer install
docker exec -it one-rrss-php php artisan key:generate
docker exec -it one-rrss-php php artisan migrate
cd frontend && pnpm install && pnpm dev
```

- API: http://localhost:48000
- Frontend: http://localhost:3002
- Mailpit: http://localhost:48025
- Health: http://localhost:48000/api/health

## Estructura

```
.
├── plan.md                # brief original
├── architecture.md        # diseño completo (24 secciones)
├── docker-compose.yml
├── docker/{nginx,php,postgres}/
├── fonts/                 # Euclid Circular B (woff2)
├── backend/               # Laravel 13.6
└── frontend/              # Next.js 16.2
```
