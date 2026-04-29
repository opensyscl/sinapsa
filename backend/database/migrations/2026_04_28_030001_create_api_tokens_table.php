<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 120);
            // Prefijo visible (no secreto): sk_live_AbCdEf12... o sk_test_...
            // Lo mostramos en la UI para que el operador identifique tokens.
            $table->string('prefix', 24)->index();

            // Hash SHA256 del token completo. NUNCA guardamos el plaintext.
            $table->string('token_hash', 64)->unique();

            // Scopes: ["messages:write","messages:read","conversations:read",...]
            $table->jsonb('scopes');

            // Modo del token (para distinguir live vs test en logs y rate-limits diferenciados)
            $table->string('mode', 8)->default('live'); // live | test

            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
