<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `messages` particionada por mes (PostgreSQL native partitioning).
 *
 * Por qué desde día 1: a 50k msg/día por workspace top, una tabla unitable se
 * convierte en un dolor a los 6 meses. Particionar a posteriori obliga a
 * reescribir queries y migrar datos. Mejor pagar la "complejidad" ahora.
 *
 * Restricciones de particionado:
 * - Cualquier UNIQUE constraint debe incluir la clave de particionado (created_at).
 *   Por eso external_id no es UNIQUE en schema; la idempotencia se hace en el job
 *   con un upsert basado en (workspace_id, external_id) dentro del rango temporal.
 * - La PRIMARY KEY también incluye created_at: (id, created_at).
 *
 * Crea las particiones del mes actual + el siguiente. El scheduler (Fase 4) creará
 * las particiones futuras automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabla padre particionada
        DB::statement("
            CREATE TABLE messages (
                id BIGSERIAL NOT NULL,
                workspace_id BIGINT NOT NULL,
                conversation_id BIGINT NOT NULL,
                channel_id BIGINT NOT NULL,
                contact_id BIGINT NOT NULL,

                direction VARCHAR(16) NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'queued',
                type VARCHAR(24) NOT NULL,

                external_id VARCHAR(255),
                client_idempotency_key VARCHAR(255),

                body TEXT,
                media_url TEXT,
                media_mime VARCHAR(120),

                template_name VARCHAR(255),
                template_payload JSONB,

                raw_payload JSONB,

                error_code VARCHAR(64),
                error_message TEXT,

                sent_at TIMESTAMP,
                delivered_at TIMESTAMP,
                read_at TIMESTAMP,
                failed_at TIMESTAMP,

                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP,

                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at);
        ");

        // Índices a nivel padre (se replican a particiones)
        DB::statement('CREATE INDEX messages_workspace_created_idx ON messages (workspace_id, created_at DESC);');
        DB::statement('CREATE INDEX messages_conversation_created_idx ON messages (conversation_id, created_at DESC);');
        DB::statement('CREATE INDEX messages_external_id_idx ON messages (workspace_id, external_id) WHERE external_id IS NOT NULL;');
        DB::statement("CREATE INDEX messages_status_idx ON messages (status) WHERE status IN ('queued', 'failed');");

        // Crea particiones de los próximos 3 meses (mes actual + 2 futuros)
        $this->createMonthlyPartition(now()->startOfMonth());
        $this->createMonthlyPartition(now()->addMonth()->startOfMonth());
        $this->createMonthlyPartition(now()->addMonths(2)->startOfMonth());
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS messages CASCADE;');
    }

    /**
     * Helper: crea la partición mensual para el primer día del mes dado.
     * Se reutiliza desde la migración y desde el job mensual (Fase 4).
     */
    private function createMonthlyPartition(\Carbon\Carbon $monthStart): void
    {
        $name = 'messages_' . $monthStart->format('Y_m');
        $from = $monthStart->toDateString();
        $to = $monthStart->copy()->addMonth()->toDateString();

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$name}
            PARTITION OF messages
            FOR VALUES FROM ('{$from}') TO ('{$to}');
        ");
    }
};
