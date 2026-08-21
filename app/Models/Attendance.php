<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single gate check-in/check-out event.
 *
 * @property int $id
 * @property int|null $gym_id
 * @property int $member_id
 * @property int|null $device_id
 * @property string $direction
 * @property string $method
 * @property float|null $confidence
 * @property \Illuminate\Support\Carbon $recognized_at
 * @property string $source
 * @property string $dedupe_hash
 */
class Attendance extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceFactory> */
    use BelongsToGym, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'member_id',
        'device_id',
        'direction',
        'method',
        'confidence',
        'recognized_at',
        'source',
        'dedupe_hash',
    ];

    protected function casts(): array
    {
        return [
            'recognized_at' => 'datetime',
            'confidence' => 'decimal:2',
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

    /**
     * A stable hash identifying "this exact event" for idempotent retries
     * from a device replaying its offline buffer.
     */
    public static function makeDedupeHash(int $memberId, ?int $deviceId, string $direction, string $recognizedAt): string
    {
        return hash('sha256', implode('|', [$memberId, $deviceId ?? 0, $direction, $recognizedAt]));
    }
}
