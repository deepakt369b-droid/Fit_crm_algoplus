<?php

namespace App\Models;

use App\Enums\Status;
use App\Models\Concerns\BelongsToGym;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use BelongsToGym, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'photo',
        'name',
        'email',
        'status',
        'password',
        'contact',
        'dob',
        'gender',
        'address',
        'country',
        'city',
        'state',
        'pincode',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'dob' => 'date',
            'status' => Status::class,
        ];
    }

    protected $dates = ['deleted_at'];

    /**
     * Get the followUps for the user.
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Get the enquiries for the user.
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    /**
     * Get the URL for the user's Filament avatar.
     *
     * @return string|null The URL of the user's avatar or null if not set.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url((string) $this->photo) : null;
    }

    /**
     * Determine if the user can access the Filament panel.
     *
     * The superadmin panel is restricted to the super_admin role; the main
     * admin panel remains open to any authenticated user (branch-level
     * access is then enforced by tenancy and policies).
     *
     * @param  Panel  $panel  The Filament panel instance.
     * @return bool True if the user can access the panel, false otherwise.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return $this->hasRole('super_admin');
        }

        return true;
    }

    /**
     * The branches (gyms) this user may switch into within the admin panel.
     *
     * Super admins may switch into every branch; everyone else is confined
     * to their own home branch.
     *
     * @return Collection<int, Gym>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->hasRole('super_admin')) {
            return Gym::query()->orderBy('name')->get();
        }

        return $this->gym ? Collection::make([$this->gym]) : Collection::make();
    }

    /**
     * Determine if the user may switch into the given branch (tenant).
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $tenant instanceof Gym && $this->gym_id !== null && $this->gym_id === $tenant->id;
    }
}
