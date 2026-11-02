<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de cada llamada a la API pública (/api/v1/*) con su canal o integración.
 * Lo ve el cliente en el panel → Registros. Retención: 30 días (model:prune).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Quién llamó: token sk_ (cliente SaaS) o usuario del panel (playground)
            $table->foreignId('api_token_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Sin FK: se guarda el id pedido aunque no exista (útil para ver 404 en el log)
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->unsignedBigInteger('integration_id')->nullable();

            $table->string('method', 8);
            $table->string('path', 512);
            $table->string('route_name', 191)->nullable();
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->string('error_code', 120)->nullable();
            $table->string('ip', 45)->nullable();

            $table->jsonb('request_body')->nullable();
            $table->jsonb('response_body')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['channel_id', 'created_at']);
            $table->index(['integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
