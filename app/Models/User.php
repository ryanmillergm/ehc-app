<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasName, HasTenants
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
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
        return $this->hasVerifiedEmail() && $this->hasRole(['Super Admin', 'Admin', 'Writer']);
    }


    // RELATIONS:


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
}
