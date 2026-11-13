<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'phone',
        'primary_role',
        'is_super_admin',
        'password',
        'security_pin',
        'is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'security_pin',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'security_pin' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->isSuperAdmin()) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                    'The Primary Super Admin account cannot be deleted.'
                );
            }
        });

        static::updating(function (User $user) {
            if ($user->getOriginal('is_super_admin') && !$user->is_super_admin) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                    'The Super Admin identity privilege cannot be stripped.'
                );
            }
            if ($user->getOriginal('is_super_admin') && !$user->is_active) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                    'The Primary Super Admin account cannot be deactivated.'
                );
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function isVisitor(): bool
    {
        return $this->primary_role === 'visitor';
    }

    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : explode('|', $roles);

        if (in_array($this->primary_role, $roleList)) {
            return true;
        }

        if (in_array($this->primary_role, ['owner', 'admin']) && !in_array('visitor', $roleList)) {
            return true;
        }

        return $this->roles->contains(fn ($r) => in_array($r->name, $roleList));
    }

    public function hasPermission(string $permissionName): bool
    {
        if (in_array($this->primary_role, ['owner', 'admin'])) {
            return true;
        }

        foreach ($this->roles as $role) {
            if ($role->permissions->contains('name', $permissionName)) {
                return true;
            }
        }

        return false;
    }
}
