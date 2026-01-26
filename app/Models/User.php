<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasName, HasTenants, MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'two_factor_confirmed_at',
        'current_team_id',
        'profile_photo_path'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'full_name',
        'initials',
        'navbar_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['first_name'] . ' ' . $attributes['last_name'],
        );
    }

    /**
     * Initials accessor (e.g. "RM").
     */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                $base = trim(
                    ($attributes['first_name'] ?? '') . ' ' . ($attributes['last_name'] ?? '')
                );

                if ($base === '' && isset($attributes['email'])) {
                    $base = $attributes['email'];
                }

                $parts = preg_split('/\s+/', $base, -1, PREG_SPLIT_NO_EMPTY);

                $initials = '';
                if (! empty($parts)) {
                    $initials = mb_substr($parts[0], 0, 1);
                    if (count($parts) > 1) {
                        $initials .= mb_substr(end($parts), 0, 1);
                    }
                }

                return mb_strtoupper($initials ?: 'U');
            },
        );
    }

    /**
     * Navbar-specific photo URL.
     * Returns NULL when there is no custom uploaded photo
     * so the UI can show initials avatar instead.
     */
    protected function navbarPhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profile_photo_path ? $this->profile_photo_url : null,
        );
    }

    // Optional helper to always return something:
    public function getPrimaryAddressOrFirstAttribute(): ?Address
    {
        return $this->primaryAddress()->first()
            ?? $this->addresses()->first();
    }
    

    // FILAMENT FUNCTIONS:

    // Filament Multi-Tenancy
    // assignedTeams

    public function getTenants(Panel $panel): Collection
    {
        return $this->assignedTeams;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->assignedTeams()->whereKey($tenant)->exists();
    }
    // End Multi-Tenancy

    /**
     * getFilamentName
     *
     * @return string
     */
    public function getFilamentName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * canAccessPanel
     *
     * @param  mixed $panel
     * @return bool
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole(['Super Admin']) || $this->hasPermissionTo('admin.panel');
        } else {
            return $this->hasRole(['Super Admin']) || $this->hasPermissionTo('org.panel');
        }
    }


    // RELATIONS:

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function primaryAddress()
    {
        return $this->hasOne(Address::class)->where('is_primary', true);
    }

    /**
     * Get the teams a user owns or has created.
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * The teams that a user belongs to.
     */
    public function assignedTeams()
    {
        return $this->belongsToMany(Team::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $notification = new class extends VerifyEmail implements ShouldQueue {};
        $this->notify($notification);
    }
}
