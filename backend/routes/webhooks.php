<?php

use App\Http\Controllers\Webhooks\MetaWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes — Meta
|--------------------------------------------------------------------------
| Este grupo se monta sin el middleware `api` (sin throttle, sin Sanctum).
| La autenticación se hace por firma HMAC dentro del controller.
|
| El channelType se pasa como path param y MetaWebhookController busca el
| adapter correcto en el registry. Para añadir un canal nuevo (Telegram, etc.):
|   1) Crear adapter que implementa ChannelAdapter
|   2) Registrarlo en ChannelAdapterRegistry::__construct()
|   3) Añadir una línea aquí
*/

Route::prefix('meta')->name('webhooks.meta.')->group(function () {
    foreach (['whatsapp', 'instagram', 'messenger'] as $type) {
        Route::get($type, [MetaWebhookController::class, 'verify'])
            ->defaults('channelType', $type)
            ->name("{$type}.verify");
        Route::post($type, [MetaWebhookController::class, 'receive'])
            ->defaults('channelType', $type)
            ->name("{$type}.receive");
    }
});
