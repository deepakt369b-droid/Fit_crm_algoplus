<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A biometric access-gate device (face/fingerprint hardware) paired to a
 * branch. Authenticates over the API as a Sanctum tokenable scoped to a
 * single ability ('attendance:write'), never as a User — so a stolen
 * device token cannot reach member, billing, or admin endpoints, all of
 * which are gated by Spatie permissions this model never holds.
 *
 * @property int $id
 * @property int|null $gym_id
 * @property string $name
 * @property string $type
 * @property string|null $location
 * @property string|null $serial
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $pairing_expires_at
 * @property \Illuminate\Support\Carbon|null $paired_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property string|null $firmware_version
 */
class Device extends Model implements AuthenticatableContract
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use Authenticatable, Authorizable, BelongsToGym, HasApiTokens, HasFactory, HasRoles, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'name',
        'type',
        'location',
        'serial',
        'status',
        'pairing_code_hash',
        'pairing_expires_at',
        'paired_at',
        'last_seen_at',
        'firmware_version',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'pairing_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'pairing_expires_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Generate a new plaintext pairing code, store only its hash, and set a
     * 15-minute expiry. Returns the plaintext code to show the admin (in
     * the QR payload and as plain text) — it is never persisted or logged.
     */
    public function generatePairingCode(): string
    {
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'pairing_code_hash' => hash('sha256', $code),
            'pairing_expires_at' => now()->addMinutes(15),
            'status' => 'pending',
        ])->save();

        return $code;
    }

    public function pairingCodeMatches(string $code): bool
    {
        if ($this->pairing_code_hash === null || $this->pairing_expires_at === null) {
            return false;
        }

        if ($this->pairing_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->pairing_code_hash, hash('sha256', $code));
    }

    public function markPaired(string $serial): void
    {
        $this->forceFill([
            'serial' => $serial,
            'status' => 'paired',
            'paired_at' => now(),
            'last_seen_at' => now(),
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
        ])->save();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    /**
     * @return HasMany<MemberDeviceIdentifier, $this>
     */
    public function memberIdentifiers(): HasMany
    {
        return $this->hasMany(MemberDeviceIdentifier::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
