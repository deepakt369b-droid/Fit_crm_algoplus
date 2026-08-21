<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A one-off or scheduled template-message campaign sent to many contacts.
 *
 * @property int $id
 * @property int|null $gym_id
 * @property int $wa_phone_number_id
 * @property int $wa_template_id
 * @property string $name
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $created_by
 * @property int $total_recipients
 * @property int $sent_count
 * @property int $delivered_count
 * @property int $read_count
 * @property int $failed_count
 */
class WhatsappBroadcast extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappBroadcastFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_broadcasts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'wa_phone_number_id',
        'wa_template_id',
        'name',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'created_by',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Auto-fill created_by from the acting user, same as BelongsToGym
     * auto-fills gym_id — the Filament create form doesn't (and
     * shouldn't) expose this as an editable field.
     */
    protected static function booted(): void
    {
        static::creating(function (self $broadcast): void {
            if ($broadcast->created_by === null && auth()->check()) {
                $broadcast->created_by = auth()->id();
            }
        });
    }

    /**
     * @return BelongsTo<WhatsappPhoneNumber, $this>
     */
    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'wa_phone_number_id');
    }

    /**
     * @return BelongsTo<WhatsappTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'wa_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WhatsappBroadcastRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappBroadcastRecipient::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }
}
