<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * System superadmin account on the central domain. Never mixed with the
 * per-campus users tables inside tenant schemas.
 */
class CentralUser extends Authenticatable
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'central_users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
