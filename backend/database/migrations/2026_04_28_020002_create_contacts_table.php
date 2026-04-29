<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->index(); // E.164
            $table->string('email')->nullable()->index();
            $table->string('avatar_url')->nullable();

            // Identidad cross-canal: { "wa": "+34666...", "ig": "ig_id_xxx", "fb": "psid_yyy" }
            $table->jsonb('identifiers')->nullable();
            // Atributos custom del cliente final (campos definidos por el workspace)
            $table->jsonb('attributes')->nullable();
            // Opt-ins explícitos: { "wa_marketing": true, "wa_marketing_at": "2026-04-28T..." }
            $table->jsonb('opt_ins')->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Un mismo phone solo puede existir una vez por workspace (cuando phone no es null)
            $table->unique(['workspace_id', 'phone'], 'contacts_workspace_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
