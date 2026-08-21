<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A branch (gym location) in a multi-branch FitCRM install.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $email
 * @property string|null $contact
 * @property string|null $address
 * @property string|null $country
 * @property string|null $state
 * @property string|null $city
 * @property string|null $pincode
 * @property string $timezone
 * @property string $currency
 * @property string $status
 */
class Gym extends Model
{
    /** @use HasFactory<\Database\Factories\GymFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'email',
        'contact',
        'address',
        'country',
        'state',
        'city',
        'pincode',
        'timezone',
        'currency',
        'status',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
