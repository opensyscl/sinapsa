<?php

namespace App\Models\Concerns;

use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenancy single-DB: every tenant-owned model uses this trait.
 *
 * - Adds belongsTo(Workspace) relation.
 * - Auto-fills workspace_id on create from the current authenticated user.
 * - Registers WorkspaceScope, which filters every query by the auth user's workspace.
 *
 * Super-admin (user with workspace_id = null) bypasses the scope and sees every row.
 */
trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function ($model) {
            if (! $model->workspace_id && auth()->check() && auth()->user()->workspace_id) {
                $model->workspace_id = auth()->user()->workspace_id;
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
