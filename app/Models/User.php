<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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

    public static function roleOptions()
    {
        return [
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_ADMIN_HR => 'Admin HR',
            self::ROLE_SECURITY => 'Security',
            self::ROLE_AUDITOR => 'Auditor',
        ];
    }

    public static function statusOptions()
    {
        return [
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_INACTIVE => 'Nonaktif',
        ];
    }

    public function roleLabel()
    {
        return static::roleOptions()[$this->role] ?? $this->role;
    }

    public function statusLabel()
    {
        return static::statusOptions()[$this->status] ?? $this->status;
    }

    public static function routeRoles()
    {
        return [
            'dashboard' => self::dashboardRoles(),
            'users.index' => [
                self::ROLE_SUPER_ADMIN,
            ],
            'users.create' => [
                self::ROLE_SUPER_ADMIN,
            ],
            'users.store' => [
                self::ROLE_SUPER_ADMIN,
            ],
            'users.show' => [
                self::ROLE_SUPER_ADMIN,
            ],
            'users.edit' => [
                self::ROLE_SUPER_ADMIN,
            ],
            'users.update' => [
                self::ROLE_SUPER_ADMIN,
            ],
            'users.destroy' => [
                self::ROLE_SUPER_ADMIN,
            ],
            'road-segments.index' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'road-segments.map' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'road-segments.map.update' => [
                self::ROLE_ADMIN_HR,
            ],
            'road-segments.map.reset' => [
                self::ROLE_ADMIN_HR,
            ],
            'road-segments.create' => [self::ROLE_ADMIN_HR],
            'road-segments.store' => [self::ROLE_ADMIN_HR],
            'road-segments.edit' => [self::ROLE_ADMIN_HR],
            'road-segments.update' => [self::ROLE_ADMIN_HR],
            'road-segments.activate' => [self::ROLE_ADMIN_HR],
            'road-segments.deactivate' => [self::ROLE_ADMIN_HR],
            'imports.index' => [
                self::ROLE_ADMIN_HR,
            ],
            'imports.store' => [
                self::ROLE_ADMIN_HR,
            ],
            'imports.commit' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.index' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'permits.show' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'permits.edit' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.update' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.deactivate' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.reactivate' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.destroy' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.clear-all' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.review.edit' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.review.update' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.review.activate' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.route-map.show' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'permits.qr.generate' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.qr.bulk-generate' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.qr.show' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.qr.print' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.qr.renew' => [
                self::ROLE_ADMIN_HR,
            ],
            'scan.index' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_SECURITY,
            ],
            'scan.verify' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_SECURITY,
            ],
            'reports.permits.index' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'reports.permits.export' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'reports.scans.index' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
            ],
            'reports.scans.export' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_AUDITOR,
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
