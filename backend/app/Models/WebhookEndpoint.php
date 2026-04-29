<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    use BelongsToWorkspace, HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_FAILING = 'failing';

    public const FAIL_THRESHOLD = 6;

    protected $fillable = [
        'workspace_id', 'created_by_user_id',
        'url', 'description',
        'events', 'secret_encrypted',
        'status', 'last_success_at', 'last_failure_at', 'consecutive_failures',
    ];

    protected $casts = [
        'events' => 'array',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'consecutive_failures' => 'integer',
    ];

    protected $hidden = ['secret_encrypted'];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }

    public function getSecret(): ?string
    {
        if (! $this->secret_encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($this->secret_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setSecret(string $plain): void
    {
        $this->secret_encrypted = Crypt::encryptString($plain);
    }

    /**
     * Genera un secret seguro estilo Stripe: `whsec_` + 32 chars random.
     * Solo se devuelve UNA vez al crear el endpoint.
     */
    public static function generateSecret(): string
    {
        return 'whsec_' . Str::random(32);
    }

    public function isSubscribedTo(string $eventType): bool
    {
        $events = $this->events ?? [];
        if (empty($events) || in_array('*', $events, true)) {
            return true;
        }
        if (in_array($eventType, $events, true)) {
            return true;
        }
        // Wildcard por familia: "message.*" cubre "message.received"/"message.sent"/...
        [$family] = explode('.', $eventType, 2) + [null, null];
        if ($family && in_array("{$family}.*", $events, true)) {
            return true;
        }

        return false;
    }

    public function recordSuccess(): void
    {
        $this->forceFill([
            'last_success_at' => now(),
            'consecutive_failures' => 0,
            'status' => $this->status === self::STATUS_FAILING ? self::STATUS_ACTIVE : $this->status,
        ])->save();
    }

    public function recordFailure(): void
    {
        $this->increment('consecutive_failures');
        $this->forceFill([
            'last_failure_at' => now(),
            'status' => $this->consecutive_failures >= self::FAIL_THRESHOLD
                ? self::STATUS_FAILING
                : $this->status,
        ])->save();
    }
}
