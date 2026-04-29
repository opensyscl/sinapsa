<?php

namespace App\Connect;

use App\Models\ConnectSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Firma y verifica los JWT de Connect Sessions.
 *
 * El JWT es una representación compacta auto-contenida del session — el cliente SaaS
 * lo pasa al frontend (browser), el frontend al hosted page de Sinapsa, y nosotros
 * lo validamos sin tocar DB SI la firma es válida y no ha expirado. Por defensa en
 * profundidad TAMBIÉN validamos contra `connect_sessions.jti` para poder revocar
 * antes del expiry si alguna vez hace falta.
 *
 * Implementación HS256 a mano para no añadir dependencia. Secret = APP_KEY.
 */
class ConnectSessionTokenService
{
    public function issue(ConnectSession $session): string
    {
        return $this->encode([
            'iss' => 'sinapsa',
            'jti' => $session->jti,
            'sub' => (string) $session->id,
            'ws' => $session->workspace_id,
            'iat' => time(),
            'exp' => $session->expires_at->getTimestamp(),
        ]);
    }

    /**
     * Decodifica y verifica firma + expiry. Devuelve los claims o lanza.
     */
    public function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed JWT');
        }
        [$h64, $p64, $s64] = $parts;

        $secret = $this->secret();
        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', "{$h64}.{$p64}", $secret, true),
        );
        if (! hash_equals($expectedSig, $s64)) {
            throw new RuntimeException('Invalid signature');
        }

        $payload = json_decode($this->base64UrlDecode($p64), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Malformed payload');
        }
        if (($payload['iss'] ?? null) !== 'sinapsa') {
            throw new RuntimeException('Invalid issuer');
        }
        if (! isset($payload['exp']) || time() >= (int) $payload['exp']) {
            throw new RuntimeException('Token expired');
        }

        return $payload;
    }

    public static function newJti(): string
    {
        return (string) Str::ulid();
    }

    public static function defaultExpiry(): Carbon
    {
        // 15 min — el usuario final tiene tiempo de sobra para terminar el popup,
        // y si abandona no podemos reusar la sesión.
        return now()->addMinutes(15);
    }

    private function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h64 = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p64 = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', "{$h64}.{$p64}", $this->secret(), true);

        return "{$h64}.{$p64}." . $this->base64UrlEncode($sig);
    }

    private function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $b64): string
    {
        $padded = str_pad(strtr($b64, '-_', '+/'), strlen($b64) % 4 ? strlen($b64) + (4 - strlen($b64) % 4) : strlen($b64), '=');

        return (string) base64_decode($padded, true);
    }

    private function secret(): string
    {
        $key = (string) config('app.key');
        // APP_KEY suele venir como base64:xxx. Le quitamos el prefijo si existe.
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }
}
