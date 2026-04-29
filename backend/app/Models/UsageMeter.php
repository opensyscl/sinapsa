<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador ligero por workspace + mes + tipo de uso. NO bloquea (no es PlanGate).
 * Sinapsa lo usa solo para ver tráfico y, llegado el día, facturar markup.
 */
class UsageMeter extends Model
{
    use HasFactory;

    public const KIND_INBOUND = 'inbound';
    public const KIND_OUTBOUND = 'outbound';

    protected $fillable = ['workspace_id', 'period', 'kind', 'channel_type', 'count'];

    protected $casts = [
        'count' => 'integer',
    ];

    /**
     * Incrementa atómicamente el contador del periodo actual. Crea el row
     * si no existe (UPSERT). Llamado desde:
     *   - ProcessIncomingMetaWebhook (kind='inbound')
     *   - SendOutboundMessage (kind='outbound', solo cuando Meta acepta)
     */
    public static function bump(int $workspaceId, string $kind, string $channelType, int $by = 1): void
    {
        $period = now()->format('Y-m');

        \DB::statement(
            'INSERT INTO usage_meters (workspace_id, period, kind, channel_type, count, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON CONFLICT (workspace_id, period, kind, channel_type)
             DO UPDATE SET count = usage_meters.count + EXCLUDED.count, updated_at = NOW()',
            [$workspaceId, $period, $kind, $channelType, $by],
        );
    }
}
