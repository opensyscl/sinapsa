<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log crudo de webhooks Meta antes de procesarlos.
 * NO usa BelongsToWorkspace porque puede no haber workspace todavía
 * (caso: webhook llega antes de que un canal esté conectado).
 */
class WebhookInboundLog extends Model
{
    protected $table = 'webhook_inbound_log';

    public $timestamps = false;

    protected $fillable = [
        'source', 'dedupe_key', 'workspace_id', 'channel_id',
        'signature_valid', 'payload', 'headers',
        'processed_at', 'processing_error', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
