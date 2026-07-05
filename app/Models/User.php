<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN_HR = 'admin_hr';
    public const ROLE_SECURITY = 'security';
    public const ROLE_AUDITOR = 'auditor';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function hasRole($role)
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles)
    {
        return in_array($this->role, $roles, true);
    }

    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public static function dashboardRoles()
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN_HR,
            self::ROLE_SECURITY,
            self::ROLE_AUDITOR,
        ];
    }

    public static function routeRoles()
    {
        return [
            'dashboard' => self::dashboardRoles(),
            'road-segments.index' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'imports.index' => [
                self::ROLE_ADMIN_HR,
            ],
            'imports.store' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.index' => [
                self::ROLE_ADMIN_HR,
            ],
            'scan.index' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_SECURITY,
            ],
        ];
    }

    public static function rolesForRoute($routeName)
    {
        $routeRoles = static::routeRoles();

        return $routeRoles[$routeName] ?? [];
    }

    public function canAccessRoute($routeName)
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->hasRole(self::ROLE_SUPER_ADMIN)) {
            return true;
        }

        return $this->hasAnyRole(static::rolesForRoute($routeName));
    }
}
