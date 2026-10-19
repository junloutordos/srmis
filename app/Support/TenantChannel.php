<?php

namespace App\Support;

/**
 * Broadcast channel names, namespaced by tenant.
 *
 * User and conversation ids are per-campus auto-increments, but every campus
 * shares one Soketi app — an un-prefixed "user.5" would be the same channel
 * for user #5 of every campus.
 */
class TenantChannel
{
    public static function user(int $userId, ?string $tenantId = null): string
    {
        return self::prefix($tenantId)."user.{$userId}";
    }

    public static function conversation(int $conversationId, ?string $tenantId = null): string
    {
        return self::prefix($tenantId)."conversation.{$conversationId}";
    }

    private static function prefix(?string $tenantId): string
    {
        return 'tenant.'.($tenantId ?? tenant('id') ?? 'none').'.';
    }
}
