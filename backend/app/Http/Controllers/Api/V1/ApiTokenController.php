<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiTokenResource;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestión de tokens de la API pública. SOLO accesible vía Sanctum
 * (humanos del dashboard) — los tokens NO pueden crear/revocar otros tokens.
 */
class ApiTokenController extends Controller
{
    public const AVAILABLE_SCOPES = [
        '*',
        'messages:read', 'messages:write',
        'conversations:read', 'conversations:write',
        'contacts:read', 'contacts:write',
        'channels:read', 'channels:write',
        'templates:read',
        'webhooks:read', 'webhooks:write',
    ];

    public function index(Request $request): JsonResponse
    {
        $tokens = ApiToken::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => ApiTokenResource::collection($tokens),
            'available_scopes' => self::AVAILABLE_SCOPES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(self::AVAILABLE_SCOPES)],
            'mode' => ['nullable', Rule::in([ApiToken::MODE_LIVE, ApiToken::MODE_TEST])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $issued = ApiToken::issue(
            workspaceId: $request->user()->workspace_id,
            name: $data['name'],
            scopes: $data['scopes'],
            createdByUserId: $request->user()->id,
            mode: $data['mode'] ?? ApiToken::MODE_LIVE,
            expiresAt: isset($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null,
        );

        return response()->json([
            // El plaintext SOLO aparece aquí, una vez. El frontend tiene que
            // mostrarlo y obligar al usuario a copiarlo.
            'plain_token' => $issued['plain'],
            'token' => new ApiTokenResource($issued['token']->load('createdBy')),
        ], 201);
    }

    public function destroy(Request $request, ApiToken $apiToken): JsonResponse
    {
        // Aseguramos que el token sea del workspace del caller
        abort_if(
            $apiToken->workspace_id !== $request->user()->workspace_id,
            404,
        );

        $apiToken->revoke();

        return response()->json(['ok' => true]);
    }
}
