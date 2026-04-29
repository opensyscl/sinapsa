<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToWorkspace, HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_SNOOZED = 'snoozed';

    protected $fillable = [
        'workspace_id', 'channel_id', 'contact_id',
        'external_thread_id', 'status', 'assigned_to_user_id',
        'last_message_at', 'snoozed_until', 'unread_count', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Último mensaje de la conversación. Útil para el listado de la bandeja
     * (`with('latestMessage')` evita N+1 sin tener que cargar todo el historial).
     */
    public function latestMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    /**
     * Última hora de mensaje INBOUND. Sirve para enforcear la ventana 24h de WA:
     * fuera de esa ventana solo se permiten plantillas APPROVED.
     */
    public function lastInboundAt(): ?\Carbon\Carbon
    {
        $iso = data_get($this->metadata, 'last_inbound_at');

        return $iso ? \Carbon\Carbon::parse($iso) : null;
    }
}
