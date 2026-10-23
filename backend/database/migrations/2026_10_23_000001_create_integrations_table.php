<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `integrations` — cuentas de terceros NO mensajería (Google Calendar, Google Ads...)
 * conectadas por un usuario final vía Connect Session + OAuth.
 *
 * Separado de `channels` a propósito: un canal tiene conversaciones y mensajes;
 * una integración es una cuenta sobre la que Sinapsa hace de proxy (agenda, ads).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // provider: google
            $table->string('provider', 32)->index();
            // products habilitados con los scopes concedidos: ["calendar"], luego "ads"
            $table->jsonb('products');
            // ID estable de la cuenta en el provider (Google: `sub` del id_token)
            $table->string('external_id')->index();
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();

            // status: connected | disconnected | error
            $table->string('status', 32)->default('connected');
            $table->jsonb('granted_scopes')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();

            // Lo que el cliente SaaS mandó en la Connect Session, para mapear a su usuario
            $table->jsonb('client_metadata')->nullable();
            $table->jsonb('config')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'provider', 'external_id'], 'integrations_workspace_provider_external_unique');
        });

        Schema::table('connect_sessions', function (Blueprint $table) {
            $table->foreignId('completed_integration_id')->nullable()
                ->after('completed_channel_id')
                ->constrained('integrations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('connect_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_integration_id');
        });
        Schema::dropIfExists('integrations');
    }
};
