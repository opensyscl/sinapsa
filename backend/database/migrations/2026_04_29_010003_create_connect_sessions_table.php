<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `connect_sessions` — autorización single-use para que un usuario final
 * del cliente SaaS conecte su cuenta Meta vía Embedded Signup.
 *
 * Flujo:
 *  1. Cliente SaaS crea la sesión con su sk_live_ → recibe `token` (JWT) + `hosted_url`
 *  2. Frontend del cliente abre `hosted_url` en popup (o usa el JS SDK)
 *  3. La hosted page valida el token, dispara FB SDK Embedded Signup
 *  4. Tras autorizar, `complete()` ejecuta el flujo Embedded Signup y persiste el Channel
 *  5. La hosted page hace postMessage al window.opener con el Channel resultante
 *
 * El token JWT se valida server-side contra esta tabla (defensa en profundidad
 * por si alguna vez hay que revocar antes del expiry).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_token_id')->nullable()->constrained('api_tokens')->nullOnDelete();

            // El "subject" del JWT — un identificador opaco que el cliente SaaS
            // puede usar para mapear su usuario final al canal resultante.
            $table->string('jti', 64)->unique();

            // Channel types que esta session permite conectar
            $table->jsonb('allowed_channel_types');

            // Optional: pista para la UI del usuario final (logo, nombre)
            $table->string('display_label')->nullable();
            // Optional: URL a la que la hosted page hará redirect al terminar
            $table->string('return_url')->nullable();
            // Optional: payload arbitrario que el cliente SaaS recupera al completar
            $table->jsonb('client_metadata')->nullable();

            $table->string('status', 16)->default('pending')->index();  // pending | completed | failed | expired
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->text('error_message')->nullable();

            $table->string('client_ip', 45)->nullable();
            $table->string('client_user_agent', 512)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_sessions');
    }
};
