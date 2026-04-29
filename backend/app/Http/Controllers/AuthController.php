<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register: crea workspace + user owner. Devuelve token Sanctum + user
     * con workspace cargado (login automático). Sinapsa es Tech Provider —
     * el workspace nace `active` sin trial ni plan.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $this->resolveUniqueSlug($data['workspace_slug'] ?? null, $data['workspace_name']);

        [$user, $token] = DB::transaction(function () use ($data, $slug) {
            $workspace = Workspace::create([
                'slug' => $slug,
                'name' => $data['workspace_name'],
                'status' => Workspace::STATUS_ACTIVE,
                'contact_email' => $data['email'],
            ]);

            $user = User::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'owner',
                'last_login_at' => now(),
            ]);

            $token = $user->createToken('signup')->plainTextToken;

            return [$user, $token];
        });

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('workspace')),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('login')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('workspace')),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('workspace')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Resuelve un slug único: usa el provisto o lo deriva del nombre,
     * y si choca añade -2, -3, ... hasta encontrar libre.
     */
    private function resolveUniqueSlug(?string $provided, string $workspaceName): string
    {
        $base = $provided ?: Str::slug($workspaceName);
        if ($base === '') {
            $base = 'workspace';
        }

        $slug = $base;
        $i = 2;
        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
