<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — Sinapsa
|--------------------------------------------------------------------------
| Privado por workspace. El user solo puede suscribirse al canal
| `workspace.{id}.inbox` si pertenece a ese workspace (super-admin pasa).
*/

Broadcast::channel('workspace.{workspaceId}.inbox', function (User $user, int $workspaceId) {
    if ($user->isSuperAdmin()) {
        return true;
    }

    return (int) $user->workspace_id === $workspaceId;
});

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});
