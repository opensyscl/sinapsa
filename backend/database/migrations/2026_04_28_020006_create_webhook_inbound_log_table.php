<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log crudo de webhooks ENTRANTES de Meta.
 *
 * Por qué existe:
 * - Persistir el payload original ANTES de procesarlo nos permite reprocesar
 *   si el adapter falla / cambia / Meta rompe contratos.
 * - Detectar reenvíos de Meta (idempotencia) por dedupe_key.
 * - Auditoría: si un cliente reclama "no recibí el mensaje", podemos verificar
 *   si Meta lo entregó.
 *
 * Retención: 30 días (job de purga en Fase 4). Pasada esa ventana, lo que importa
 * vive en `messages` ya procesado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_inbound_log', function (Blueprint $table) {
            $table->id();
            // Channel type que sirvió el webhook (whatsapp/instagram/messenger)
            $table->string('source', 32)->index();
            // dedupe_key: hash del payload completo. Si Meta reenvía, descartamos.
            $table->string('dedupe_key', 64)->unique();
            // workspace_id resuelto (puede ser null si no hemos podido localizar el canal)
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();

            // signature_valid: true si la firma X-Hub-Signature-256 verificó OK
            $table->boolean('signature_valid')->default(false);

            $table->jsonb('payload');
            $table->jsonb('headers')->nullable();

            $table->timestamp('processed_at')->nullable()->index();
            $table->text('processing_error')->nullable();

            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_inbound_log');
    }
};
