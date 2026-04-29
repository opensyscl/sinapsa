<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop fields del modelo "SaaS con planes" en `workspaces`.
 *
 * Sinapsa pivota a Tech Provider puro (no vende suscripciones). El cliente SaaS
 * que se conecta a Sinapsa NO tiene plan/trial/billing — Sinapsa expone el
 * Connect-as-a-Service, ellos consumen la API y ya. Si en el futuro hay markup
 * por mensaje, lo gestionaremos con `usage_meters` + facturación externa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'plan_code',
                'billing_cycle',
                'trial_ends_at',
                'current_period_ends_at',
            ]);
        });

        // Cualquier workspace en `trialing` pasa a `active` (no hay más trials).
        \DB::statement("UPDATE workspaces SET status = 'active' WHERE status = 'trialing'");
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('plan_code')->default('starter')->after('status');
            $table->string('billing_cycle')->nullable()->after('plan_code');
            $table->timestamp('trial_ends_at')->nullable()->after('billing_cycle');
            $table->timestamp('current_period_ends_at')->nullable()->after('trial_ends_at');
        });
    }
};
