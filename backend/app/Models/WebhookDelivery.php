<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use BelongsToWorkspace, HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILING = 'failing'; // sigue reintentando
    public const STATUS_DEAD = 'dead';       // agotó retries → DLQ

    protected $fillable = [
        'workspace_id', 'endpoint_id',
        'event_id', 'event_type', 'payload',
        'attempt', 'status',
        'response_status', 'response_headers', 'response_body', 'error_message',
        'next_attempt_at', 'delivered_at', 'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_headers' => 'array',
        'attempt' => 'integer',
        'response_status' => 'integer',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }
}
