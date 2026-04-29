<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // type: whatsapp | instagram | messenger
            $table->string('type', 32)->index();
            $table->string('display_name');
            // external_id es el ID de Meta para localizar el canal:
            //  - WhatsApp Cloud: phone_number_id
            //  - Instagram:      ig_user_id
            //  - Messenger:      page_id
            $table->string('external_id')->index();
            $table->string('meta_business_id')->nullable();

            // status: connected | disconnected | error | pending
            $table->string('status', 32)->default('pending');
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('webhook_subscribed_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();

            // Config específico del canal (e.g. WA: business_phone, business_name; IG: app_scoped_id...)
            $table->jsonb('config')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Un workspace no puede tener dos canales con el mismo external_id+type
            $table->unique(['workspace_id', 'type', 'external_id'], 'channels_workspace_type_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
