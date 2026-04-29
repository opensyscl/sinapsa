<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `webhook_deliveries` particionado por mes (igual estrategia que `messages`).
 *
 * Por qué desde día 1: a 50k eventos/día la tabla crece rápido. Con particionado
 * podemos archivar/eliminar particiones antiguas sin LOCK ni VACUUM costoso.
 *
 * Restricciones particionado:
 *  - PRIMARY KEY incluye created_at: (id, created_at).
 *  - Sin UNIQUE constraints globales (Postgres no las permite cross-partition
 *    sin la clave de particionado).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE webhook_deliveries (
                id BIGSERIAL NOT NULL,
                workspace_id BIGINT NOT NULL,
                endpoint_id BIGINT NOT NULL,

                event_id VARCHAR(40) NOT NULL,         -- evt_01HK... ULID
                event_type VARCHAR(80) NOT NULL,       -- message.received, message.sent, ...

                payload JSONB NOT NULL,                -- el payload completo enviado al cliente

                attempt SMALLINT NOT NULL DEFAULT 0,   -- intento actual (0 = aún no enviado)
                status VARCHAR(16) NOT NULL DEFAULT 'pending',  -- pending|delivered|failing|dead

                response_status SMALLINT,
                response_headers JSONB,
                response_body TEXT,                    -- truncado a ~4KB en el job
                error_message TEXT,

                next_attempt_at TIMESTAMP,
                delivered_at TIMESTAMP,
                failed_at TIMESTAMP,

                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP,

                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at);
        ");

        DB::statement('CREATE INDEX webhook_deliveries_workspace_created_idx ON webhook_deliveries (workspace_id, created_at DESC);');
        DB::statement('CREATE INDEX webhook_deliveries_endpoint_created_idx ON webhook_deliveries (endpoint_id, created_at DESC);');
        DB::statement("CREATE INDEX webhook_deliveries_status_idx ON webhook_deliveries (status) WHERE status IN ('pending','failing');");
        DB::statement('CREATE INDEX webhook_deliveries_event_id_idx ON webhook_deliveries (event_id);');

        $this->createMonthlyPartition(now()->startOfMonth());
        $this->createMonthlyPartition(now()->addMonth()->startOfMonth());
        $this->createMonthlyPartition(now()->addMonths(2)->startOfMonth());
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS webhook_deliveries CASCADE;');
    }

    private function createMonthlyPartition(\Carbon\Carbon $monthStart): void
    {
        $name = 'webhook_deliveries_' . $monthStart->format('Y_m');
        $from = $monthStart->toDateString();
        $to = $monthStart->copy()->addMonth()->toDateString();

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$name}
            PARTITION OF webhook_deliveries
            FOR VALUES FROM ('{$from}') TO ('{$to}');
        ");
    }
};
