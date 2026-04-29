<?php

use App\Http\Controllers\Api\V1\ApiTokenController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ConnectSessionController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\PublicMessageController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\WebhookEndpointController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Sinapsa
|--------------------------------------------------------------------------
*/

// Health (público — load balancer y deploy probes)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'env' => config('app.env'),
        'time' => now()->toIso8601String(),
    ]);
});

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:3,10');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->get('/user', fn (Request $r) => $r->user());

/*
|--------------------------------------------------------------------------
| API v1 — autenticada via Sanctum bearer (dashboard humano)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('v1')->name('v1.')->group(function () {
    // Channels
    Route::prefix('channels')->name('channels.')->group(function () {
        Route::get('/', [ChannelController::class, 'index'])->name('index');
        Route::post('whatsapp/connect', [ChannelController::class, 'connectWhatsApp'])->name('whatsapp.connect');
        Route::post('whatsapp/connect-manual', [ChannelController::class, 'connectWhatsAppManual'])->name('whatsapp.connect_manual');
        Route::post('instagram/connect-manual', [ChannelController::class, 'connectInstagramManual'])->name('instagram.connect_manual');
        Route::post('messenger/connect-manual', [ChannelController::class, 'connectMessengerManual'])->name('messenger.connect_manual');
        Route::get('{channel}', [ChannelController::class, 'show'])->name('show');
        Route::post('{channel}/disconnect', [ChannelController::class, 'disconnect'])->name('disconnect');
        Route::get('{channel}/templates', [ChannelController::class, 'templates'])->name('templates');
        Route::post('{channel}/templates/sync', [ChannelController::class, 'syncTemplates'])->name('templates.sync');
        Route::post('{channel}/health-check', [ChannelController::class, 'healthCheck'])->name('health_check');
    });

    // Conversations + messages (bandeja)
    Route::prefix('conversations')->name('conversations.')->group(function () {
        Route::get('/', [ConversationController::class, 'index'])->name('index');
        Route::get('{conversation}', [ConversationController::class, 'show'])->name('show');
        Route::patch('{conversation}', [ConversationController::class, 'update'])->name('update');
        Route::post('{conversation}/read', [ConversationController::class, 'markRead'])->name('mark_read');
        Route::get('{conversation}/messages', [ConversationController::class, 'messages'])->name('messages');
        Route::post('{conversation}/messages', [MessageController::class, 'sendInConversation'])->name('messages.send');
    });

    // API tokens — solo dashboard humano via Sanctum
    Route::prefix('api-tokens')->name('api_tokens.')->group(function () {
        Route::get('/', [ApiTokenController::class, 'index'])->name('index');
        Route::post('/', [ApiTokenController::class, 'store'])->name('store');
        Route::delete('{apiToken}', [ApiTokenController::class, 'destroy'])->name('destroy');
    });

    // Webhook endpoints (salientes hacia CRMs externos) — solo dashboard humano
    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::get('/', [WebhookEndpointController::class, 'index'])->name('index');
        Route::post('/', [WebhookEndpointController::class, 'store'])->name('store');
        Route::get('{webhook}', [WebhookEndpointController::class, 'show'])->name('show');
        Route::patch('{webhook}', [WebhookEndpointController::class, 'update'])->name('update');
        Route::delete('{webhook}', [WebhookEndpointController::class, 'destroy'])->name('destroy');
        Route::post('{webhook}/test', [WebhookEndpointController::class, 'test'])->name('test');
        Route::get('{webhook}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('deliveries');
        Route::post('{webhook}/deliveries/{delivery}/replay', [WebhookEndpointController::class, 'replay'])->name('deliveries.replay');
    });

    // Templates WhatsApp managed
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
        Route::post('/', [TemplateController::class, 'store'])->name('store');
        Route::post('sync', [TemplateController::class, 'syncChannel'])->name('sync');
        Route::get('{template}', [TemplateController::class, 'show'])->name('show');
        Route::delete('{template}', [TemplateController::class, 'destroy'])->name('destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Test webhook receiver — DEV only
|--------------------------------------------------------------------------
| Endpoint local para que un WebhookEndpoint pueda apuntar a Sinapsa misma
| y ver el ciclo completo en local sin necesitar ngrok ni servicios externos.
| Cachea las últimas 50 entregas en Redis bajo `dev_webhook_received:{slug}`.
*/
if (app()->environment(['local', 'staging'])) {
    Route::post('__webhook-test/{slug}', function (\Illuminate\Http\Request $request, string $slug) {
        $entry = [
            'received_at' => now()->toIso8601String(),
            'event' => $request->header('X-Sinapsa-Event'),
            'event_id' => $request->header('X-Sinapsa-Event-Id'),
            'delivery' => $request->header('X-Sinapsa-Delivery'),
            'workspace' => $request->header('X-Sinapsa-Workspace'),
            'signature' => $request->header('X-Sinapsa-Signature'),
            'body' => $request->getContent(),
        ];
        $key = "dev_webhook_received:{$slug}";
        $list = Cache::get($key, []);
        array_unshift($list, $entry);
        Cache::put($key, array_slice($list, 0, 50), 3600);
        Log::info('dev_webhook_received', ['slug' => $slug] + $entry);

        return response()->json(['received' => true]);
    })->name('dev.webhook_test');
}

/*
|--------------------------------------------------------------------------
| Connect Sessions — endpoints PÚBLICOS para la Hosted Connect Page
|--------------------------------------------------------------------------
| Auth: el token JWT va en el path. Validez = firma + expiry + DB row.
*/
Route::prefix('v1/connect-sessions')->name('public.connect_sessions.')->group(function () {
    Route::get('{token}/info', [ConnectSessionController::class, 'info'])->name('info');
    Route::post('{token}/complete', [ConnectSessionController::class, 'complete'])->name('complete');
});

/*
|--------------------------------------------------------------------------
| API v1 PÚBLICA — para CRMs / bots / integradores externos
|--------------------------------------------------------------------------
| Auth: bearer `sk_live_xxx` o `sk_test_xxx` (modelo ApiToken).
| Errores tipados estilo Stripe (ApiException → JSON).
| Idempotency-Key opcional en POSTs.
| Cada endpoint declara el scope mínimo requerido.
*/
Route::middleware(['auth:api-token', 'idempotency'])->prefix('v1')->name('public.v1.')->group(function () {
    Route::get('messages', [PublicMessageController::class, 'index'])
        ->middleware('scope:messages:read')
        ->name('messages.index');
    Route::get('messages/{message}', [PublicMessageController::class, 'show'])
        ->middleware('scope:messages:read')
        ->name('messages.show');
    Route::post('messages', [PublicMessageController::class, 'send'])
        ->middleware('scope:messages:write')
        ->name('messages.send');

    // Connect Session — el cliente SaaS llama esto para iniciar el embed flow
    Route::post('connect-sessions', [ConnectSessionController::class, 'store'])
        ->middleware('scope:channels:write')
        ->name('connect_sessions.store');
});
