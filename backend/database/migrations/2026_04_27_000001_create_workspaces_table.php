<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('status')->default('trialing'); // trialing, active, past_due, suspended, cancelled
            $table->string('plan_code')->default('starter');
            $table->string('billing_cycle')->nullable(); // monthly, yearly
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();

            // Meta linkage (un Business Manager por workspace en MVP)
            $table->string('meta_business_id')->nullable();

            // Compliance / privacidad
            $table->unsignedSmallInteger('retention_days')->default(90);

            // Branding y contacto
            $table->string('logo_url')->nullable();
            $table->string('contact_email')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Ahora que workspaces existe, conectamos la FK desde users
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('workspace_id')
                ->references('id')->on('workspaces')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
        });
        Schema::dropIfExists('workspaces');
    }
};
