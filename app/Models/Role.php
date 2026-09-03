<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'display_name'];

    /**
     * The tenant-facing label for this role — falls back to the internal
     * `name` when no per-tenant override is set (e.g. OED's OCD role
     * displays as "KID Chief"; every other tenant just sees "OCD").
     */
    public function getLabelAttribute(): string
    {
        return $this->display_name ?: $this->name;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }
}
