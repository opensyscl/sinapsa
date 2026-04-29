<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();

            // Identificador del thread en el lado de Meta:
            //  - WhatsApp: el wa_id (teléfono normalizado del contact)
            //  - Instagram: conversation id
            //  - Messenger: thread id (psid)
            $table->string('external_thread_id')->index();

            // status: open | pending | closed | snoozed
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('snoozed_until')->nullable();
            $table->unsignedInteger('unread_count')->default(0);

            // Metadata: ventana 24h WA, primera respuesta, etc
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            // Único por (workspace, channel, external_thread_id)
            $table->unique(
                ['workspace_id', 'channel_id', 'external_thread_id'],
                'conversations_workspace_channel_thread_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
