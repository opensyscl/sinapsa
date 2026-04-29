<?php

namespace App\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Errores tipados de la API pública estilo Stripe.
 *
 *  {
 *    "error": {
 *      "type":    "invalid_request_error",
 *      "code":    "missing_optin",
 *      "message": "Marketing template requires WA opt-in",
 *      "param":   "to.contact_id",
 *      "doc_url": "https://docs.sinapsa.app/errors/missing_optin"
 *    }
 *  }
 *
 * Tipos válidos (para que clientes los pinten correctamente):
 *  - invalid_request_error  → 400/422 — body / params inválidos
 *  - authentication_error   → 401     — token ausente / hash incorrecto / revocado
 *  - permission_error       → 403     — token sin scope o tocando recurso de otro workspace
 *  - rate_limit_error       → 429     — demasiadas requests
 *  - api_error              → 5xx     — fallo nuestro / Meta caído
 */
class ApiException extends RuntimeException implements Responsable
{
    public const TYPE_INVALID_REQUEST = 'invalid_request_error';
    public const TYPE_AUTHENTICATION = 'authentication_error';
    public const TYPE_PERMISSION = 'permission_error';
    public const TYPE_RATE_LIMIT = 'rate_limit_error';
    public const TYPE_API = 'api_error';

    public function __construct(
        public readonly string $type,
        public readonly string $errorCode,
        string $message,
        public readonly ?string $param = null,
        public readonly ?string $docUrl = null,
        public readonly int $status = 400,
    ) {
        parent::__construct($message);
    }

    public static function invalidRequest(string $code, string $message, ?string $param = null): self
    {
        return new self(self::TYPE_INVALID_REQUEST, $code, $message, $param, status: 422);
    }

    public static function authentication(string $code, string $message): self
    {
        return new self(self::TYPE_AUTHENTICATION, $code, $message, status: 401);
    }

    public static function permission(string $code, string $message): self
    {
        return new self(self::TYPE_PERMISSION, $code, $message, status: 403);
    }

    public static function rateLimit(string $message): self
    {
        return new self(self::TYPE_RATE_LIMIT, 'rate_limited', $message, status: 429);
    }

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'error' => array_filter([
                'type' => $this->type,
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'param' => $this->param,
                'doc_url' => $this->docUrl,
            ], fn ($v) => $v !== null),
        ], $this->status);
    }
}
