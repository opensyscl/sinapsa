<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query to the authenticated user's workspace_id.
 *
 * Bypassed when:
 * - No user is authenticated (artisan, queues outside a workspace context, public routes).
 * - User is a super-admin (workspace_id = null).
 * - Caller explicitly opts out with `->withoutGlobalScope(WorkspaceScope::class)`.
 *
 * Webhook handlers and queue workers MUST resolve the workspace explicitly
 * (e.g. via the channel's external_id) and pass it down, since auth() is empty there.
 */
class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        // Super-admin (no workspace) ve todo
        if (! $user->workspace_id) {
            return;
        }

        $builder->where($model->getTable() . '.workspace_id', $user->workspace_id);
    }
}
