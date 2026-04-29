<?php

namespace App\Models;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token de la API pública del SaaS (consumido por CRMs/bots/n8n etc).
 *
 * Distinto de Sanctum (que es para users humanos del dashboard):
 *  - Formato `sk_live_<32 chars>` o `sk_test_<32>`. Estilo Stripe.
 *  - Hash SHA256 en DB. El plaintext NUNCA se persiste (solo se muestra una vez al crear).
 *  - Tiene scopes (`messages:write`, `messages:read`, `contacts:*`, etc.).
 *  - Implementa `Authenticatable` para enchufarse al guard `api-token` y que
 *    `$request->user()` devuelva esta instancia en endpoints públicos.
 *
 * El campo `workspace_id` permite que la app trate al token como si fuera el
 * "user" del workspace para fines de `BelongsToWorkspace` / `WorkspaceScope`.
 */
class ApiToken extends Model implements Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'created_by_user_id',
        'name', 'prefix', 'token_hash',
        'scopes', 'mode',
        'last_used_at', 'last_used_ip', 'expires_at', 'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['token_hash'];

    /**
     * Lo que conecta este modelo con el sistema multi-tenant: cuando un endpoint
     * pregunta `auth()->user()->workspace_id`, devolvemos el del token.
     */
    public ?int $workspace_id_attribute = null;

    public function getWorkspaceIdAttribute($value): ?int
    {
        return $value;
    }

    public function isSuperAdmin(): bool
    {
        return false; // los api tokens nunca son super-admin
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ─────────────────────────────────────────────────────────────
    // Generación y verificación
    // ─────────────────────────────────────────────────────────────

    public const MODE_LIVE = 'live';
    public const MODE_TEST = 'test';

    /**
     * Genera un nuevo plain token, lo persiste con hash y devuelve el plain
     * para mostrarlo UNA SOLA VEZ al usuario.
     *
     * @return array{token: ApiToken, plain: string}
     */
    public static function issue(
        int $workspaceId,
        string $name,
        array $scopes,
        ?int $createdByUserId = null,
        string $mode = self::MODE_LIVE,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $random = Str::random(32);
        $plain = "sk_{$mode}_{$random}";
        $hash = hash('sha256', $plain);
        $prefix = substr($plain, 0, 12); // sk_live_AbCd...

        $token = self::create([
            'workspace_id' => $workspaceId,
            'created_by_user_id' => $createdByUserId,
            'name' => $name,
            'prefix' => $prefix,
            'token_hash' => $hash,
            'scopes' => $scopes,
            'mode' => $mode,
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    /**
     * Localiza un ApiToken por su plaintext. NO devuelve si está revocado/expirado.
     */
    public static function findByToken(string $plain): ?self
    {
        if (! preg_match('/^sk_(live|test)_[A-Za-z0-9]{20,}$/', $plain)) {
            return null;
        }

        $hash = hash('sha256', $plain);
        $token = self::where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (! $token) {
            return null;
        }
        if ($token->expires_at && $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        if (in_array('*', $scopes, true)) {
            return true;
        }
        if (in_array($scope, $scopes, true)) {
            return true;
        }
        // Wildcards por familia: "messages:*" cubre "messages:write" y "messages:read"
        [$family] = explode(':', $scope, 2) + [null, null];
        if ($family && in_array("{$family}:*", $scopes, true)) {
            return true;
        }

        return false;
    }

    public function touchUsage(?string $ip): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->save();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    // ─────────────────────────────────────────────────────────────
    // Authenticatable interface — para que `auth()->user()` devuelva esto
    // ─────────────────────────────────────────────────────────────

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'token_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->token_hash;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // no-op
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
