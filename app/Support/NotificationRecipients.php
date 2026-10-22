<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Single source of truth for "who should be told about this step".
 *
 * Recipients are resolved by role (the role_user pivot), never from free-text
 * columns like position, and only include active users with a usable email —
 * deactivated accounts (status = 'inactive') are never contacted.
 */
class NotificationRecipients
{
    /** @return Collection<int, User> */
    public static function forRole(string $role): Collection
    {
        return self::forAnyRole([$role]);
    }

    /**
     * @param  string[]  $roles
     * @return Collection<int, User>
     */
    public static function forAnyRole(array $roles): Collection
    {
        return User::havingAnyRole($roles)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'inactive'))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();
    }

    /**
     * Whether a user resolved by id (request owner, division chief, assignee)
     * may be emailed. forRole()/forAnyRole() already filter this way; direct
     * lookups must go through here so deactivated accounts are never contacted.
     */
    public static function canEmail(?User $user): bool
    {
        return $user !== null
            && $user->status !== 'inactive'
            && filled($user->email);
    }
}
