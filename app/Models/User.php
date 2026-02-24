<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasApiTokens, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * User memiliki banyak roles (many-to-many)
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    /**
     * Cek apakah user punya role tertentu
     * Contoh: $user->hasRole('admin')
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    /**
     * Cek apakah user punya permission tertentu (lewat roles-nya)
     * Contoh: $user->hasPermission('stock.edit')
     */
    public function hasPermission(string $permissionName): bool
    {
        // Load roles dengan permissions jika belum di-load
        $roles = $this->roles->loadMissing('permissions');

        foreach ($roles as $role) {
            if ($role->hasPermission($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dapatkan semua permission names milik user (dari semua role-nya)
     * Berguna untuk dikirim ke frontend
     */
    public function getAllPermissions(): array
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->toArray();
    }
}
