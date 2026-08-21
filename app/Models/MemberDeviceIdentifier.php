<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a member to the identifier a specific gate device knows them by.
 * The biometric template stays on the device; this row is the mapping.
 *
 * @property int $id
 * @property int|null $gym_id
 * @property int $member_id
 * @property int $device_id
 * @property string $external_user_id
 * @property string $biometric_type
 * @property string|null $finger_position
 * @property \Illuminate\Support\Carbon|null $enrolled_at
 * @property \Illuminate\Support\Carbon|null $consent_given_at
 */
class MemberDeviceIdentifier extends Model
{
    /** @use HasFactory<\Database\Factories\MemberDeviceIdentifierFactory> */
    use BelongsToGym, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'member_id',
        'device_id',
        'external_user_id',
        'biometric_type',
        'finger_position',
        'enrolled_at',
        'consent_given_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'consent_given_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
