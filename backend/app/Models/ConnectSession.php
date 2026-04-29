<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una "Connect Session" es una autorización single-use para que un usuario final
 * del cliente SaaS conecte su cuenta Meta a través de Sinapsa.
 *
 * NO usa BelongsToWorkspace porque la mayoría de operaciones son públicas
 * (validar/completar via JWT). El workspace_id se chequea explícitamente.
 */
class ConnectSession extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'workspace_id', 'api_token_id', 'jti',
        'allowed_channel_types', 'display_label', 'return_url', 'client_metadata',
        'status', 'expires_at', 'completed_at', 'completed_channel_id', 'error_message',
        'client_ip', 'client_user_agent',
    ];

    protected $casts = [
        'allowed_channel_types' => 'array',
        'client_metadata' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'completed_channel_id');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at->isFuture();
    }
}
