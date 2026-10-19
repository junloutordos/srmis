<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| tenant.{slug}.conversation.{id} — participants only (not left)
| tenant.{slug}.user.{id}         — the user themselves only (personal notification channel)
|
| Channels are namespaced by tenant: ids are per-campus, but Soketi is shared.
|
*/

// Personal channel — used for cross-page new-message badges & browser notifications
Broadcast::channel('tenant.{tenantId}.user.{userId}', function ($user, string $tenantId, int $userId) {
    return tenant('id') === $tenantId && $user->id === $userId;
});

Broadcast::channel('tenant.{tenantId}.conversation.{conversationId}', function ($user, string $tenantId, int $conversationId) {
    if (tenant('id') !== $tenantId) {
        return false;
    }

    $conversation = Conversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    return $conversation->participants()
        ->where('users.id', $user->id)
        ->whereNull('conversation_user.left_at')
        ->exists();
});
