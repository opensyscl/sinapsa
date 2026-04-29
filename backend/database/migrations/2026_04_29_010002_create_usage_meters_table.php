<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contadores ligeros de uso por workspace y mes.
 *
 * No se usan para enforcement (Sinapsa es Tech Provider, no SaaS de planes).
 * Sirven para que el operador vea tráfico por canal y, llegado el día,
 * facturar markup per-mensaje sin tener que reescribir nada.
 *
 * Se incrementan vía `UsageMeter::bump()` desde:
 *   - ProcessIncomingMetaWebhook (kind='inbound')
 *   - SendOutboundMessage (kind='outbound', solo cuando Meta acepta)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7)->index();        // 'YYYY-MM'
            $table->string('kind', 24);                   // inbound | outbound
            $table->string('channel_type', 24);           // whatsapp | instagram | messenger
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'period', 'kind', 'channel_type'],
                'usage_meters_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_meters');
    }
};
