<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una llamada a la API pública. La escribe el middleware LogApiRequest.
 */
class ApiRequestLog extends Model
{
    use BelongsToWorkspace, MassPrunable;

    public const RETENTION_DAYS = 30;

    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'api_token_id', 'user_id', 'channel_id', 'integration_id',
        'method', 'path', 'route_name', 'status', 'duration_ms', 'error_code', 'ip',
        'request_body', 'response_body', 'created_at',
    ];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
        'created_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::withoutGlobalScopes()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class);
    }
}
