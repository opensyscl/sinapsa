<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `end_user_id` — el identificador que el cliente SaaS usa para SU usuario final
 * (opaco para Sinapsa). Viaja Connect Session → Channel / Integration y permite
 * responder "¿qué tiene conectado el usuario X del cliente Y?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connect_sessions', function (Blueprint $table) {
            $table->string('end_user_id', 191)->nullable()->index();
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->string('end_user_id', 191)->nullable()->index();
            $table->jsonb('client_metadata')->nullable();
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->string('end_user_id', 191)->nullable()->index();
        });

        // Integraciones conectadas antes de este campo: el demo mandaba client_metadata.user_id
        DB::statement("UPDATE integrations SET end_user_id = client_metadata->>'user_id' WHERE end_user_id IS NULL AND client_metadata->>'user_id' IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('integrations', fn (Blueprint $table) => $table->dropColumn('end_user_id'));
        Schema::table('channels', fn (Blueprint $table) => $table->dropColumn(['end_user_id', 'client_metadata']));
        Schema::table('connect_sessions', fn (Blueprint $table) => $table->dropColumn('end_user_id'));
    }
};
