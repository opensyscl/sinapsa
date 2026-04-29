<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sinapsa configuration
    |--------------------------------------------------------------------------
    | Settings específicos del producto. Todo lo del entorno (.env) entra por aquí.
    | Nada de leer env() directamente desde modelos / jobs / controllers.
    */

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'graph_version' => env('META_GRAPH_VERSION', 'v22.0'),
        'graph_url' => 'https://graph.facebook.com/' . env('META_GRAPH_VERSION', 'v22.0'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', 'cambiame-en-prod'),
        'wa_embedded_signup_config_id' => env('META_WA_EMBEDDED_SIGNUP_CONFIG_ID'),
        'public_webhook_url' => env('META_PUBLIC_WEBHOOK_URL'),
    ],

    'queues' => [
        // Cada cola tiene su política de retries y prioridad.
        'inbound' => 'inbound',           // alto throughput, retry suave
        'outbound' => 'outbound',         // crítico, retry agresivo
        'webhooks_out' => 'webhooks-out', // hacia clientes externos
        'media_download' => 'media-download',
        'token_refresh' => 'token-refresh',
    ],

    'whatsapp' => [
        // Ventana 24h: fuera de aquí solo se permiten plantillas APPROVED
        'customer_service_window_hours' => 24,
        // Plan free de WA Cloud: 250 conversaciones/mes con clientes nuevos
        // Plan paid: 1k convos init/24h por número (cliente puede subir techo)
        'send_rate_limit_per_second' => 80,
    ],

    'instagram' => [
        'customer_service_window_hours' => 24,
    ],

    'messenger' => [
        'customer_service_window_hours' => 168, // 7 días
    ],

    'idempotency' => [
        // TTL del store de Idempotency-Key (en Redis). 24h cubre el típico window
        // de retries de un cliente legítimo sin desbordar memoria.
        'ttl_seconds' => 86400,
    ],

    'connect' => [
        // URL base del frontend que sirve la Hosted Connect Page (`/connect?token=...`).
        // En docker compose el frontend corre en :3002 desde el host.
        'hosted_url_base' => env('SINAPSA_HOSTED_URL_BASE', env('FRONTEND_URL', 'http://localhost:3002')),
    ],
];
