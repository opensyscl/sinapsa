<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('url');
            $table->string('description')->nullable();

            // Eventos suscritos: ["message.received","message.delivered",...]
            // Si vacío o ["*"] → todos los eventos.
            $table->jsonb('events');

            // Secret HMAC cifrado at-rest. NUNCA se devuelve en una Resource.
            // El cliente lo recibe SOLO al crear el endpoint, una vez.
            $table->text('secret_encrypted');

            // active | paused | failing  (failing = auto-pausado tras 6 fallos consecutivos)
            $table->string('status', 16)->default('active')->index();

            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
