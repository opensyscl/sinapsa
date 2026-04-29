<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // e.g. "bienvenida_arrendatario"
            $table->string('language', 16); // ISO BCP-47, e.g. "es", "en_US"

            // category Meta: UTILITY | MARKETING | AUTHENTICATION
            $table->string('category', 32)->nullable();
            // status Meta: APPROVED | PENDING | REJECTED | DISABLED | PAUSED
            $table->string('status', 32)->default('PENDING');

            // Componentes (header, body, footer, buttons) tal cual los devuelve Meta
            $table->jsonb('components')->nullable();

            $table->string('meta_template_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('rejected_reason')->nullable();

            $table->timestamps();

            // Una plantilla con mismo name+language es única por canal
            $table->unique(
                ['channel_id', 'name', 'language'],
                'wa_templates_channel_name_lang_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
    }
};
