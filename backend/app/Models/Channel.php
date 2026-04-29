<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Channel extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_INSTAGRAM = 'instagram';
    public const TYPE_MESSENGER = 'messenger';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'workspace_id', 'type', 'display_name',
        'external_id', 'meta_business_id',
        'status', 'access_token_encrypted', 'refresh_token_encrypted',
        'token_expires_at', 'webhook_subscribed_at',
        'last_health_check_at', 'last_error_code', 'last_error_message',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
        'token_expires_at' => 'datetime',
        'webhook_subscribed_at' => 'datetime',
        'last_health_check_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WaTemplate::class);
    }

    /**
     * Devuelve el access_token en plano (descifrando) o null si no hay.
     * NUNCA exponer este getter en una Resource.
     */
    public function getAccessToken(): ?string
    {
        if (! $this->access_token_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Cifra y persiste el access_token. No llama a save() — el caller decide.
     */
    public function setAccessToken(string $plain): void
    {
        $this->access_token_encrypted = Crypt::encryptString($plain);
    }

    public function getRefreshToken(): ?string
    {
        if (! $this->refresh_token_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->refresh_token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRefreshToken(?string $plain): void
    {
        $this->refresh_token_encrypted = $plain ? Crypt::encryptString($plain) : null;
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }
}
