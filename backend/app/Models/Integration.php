<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * Cuenta de un tercero (Google) conectada por un usuario final del cliente SaaS.
 * Tokens cifrados at-rest igual que `Channel`.
 */
class Integration extends Model
{
    use BelongsToWorkspace, SoftDeletes;

    public const PROVIDER_GOOGLE = 'google';

    public const PRODUCT_CALENDAR = 'calendar';

    public const STATUS_CONNECTED = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'workspace_id', 'provider', 'products', 'external_id', 'email', 'display_name',
        'status', 'granted_scopes', 'access_token_encrypted', 'refresh_token_encrypted',
        'token_expires_at', 'last_error_code', 'last_error_message',
        'client_metadata', 'config', 'end_user_id',
    ];

    protected $casts = [
        'products' => 'array',
        'granted_scopes' => 'array',
        'client_metadata' => 'array',
        'config' => 'array',
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
    ];

    /** NUNCA exponer este getter en una Resource. */
    public function getAccessToken(): ?string
    {
        return $this->decrypt($this->access_token_encrypted);
    }

    public function setAccessToken(string $plain): void
    {
        $this->access_token_encrypted = Crypt::encryptString($plain);
    }

    public function getRefreshToken(): ?string
    {
        return $this->decrypt($this->refresh_token_encrypted);
    }

    public function setRefreshToken(?string $plain): void
    {
        $this->refresh_token_encrypted = $plain ? Crypt::encryptString($plain) : null;
    }

    public function hasProduct(string $product): bool
    {
        return in_array($product, $this->products ?? [], true);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    private function decrypt(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
