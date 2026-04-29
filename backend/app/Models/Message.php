<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToWorkspace, HasFactory;

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_VIDEO = 'video';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_TEMPLATE = 'template';
    public const TYPE_INTERACTIVE = 'interactive';
    public const TYPE_REACTION = 'reaction';
    public const TYPE_LOCATION = 'location';
    public const TYPE_STICKER = 'sticker';

    protected $fillable = [
        'workspace_id', 'conversation_id', 'channel_id', 'contact_id',
        'direction', 'status', 'type',
        'external_id', 'client_idempotency_key',
        'body', 'media_url', 'media_mime',
        'template_name', 'template_payload',
        'raw_payload',
        'error_code', 'error_message',
        'sent_at', 'delivered_at', 'read_at', 'failed_at',
    ];

    protected $casts = [
        'template_payload' => 'array',
        'raw_payload' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
