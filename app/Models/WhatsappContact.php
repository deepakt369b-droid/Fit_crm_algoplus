<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A WhatsApp contact — optionally linked to an existing Member or Enquiry,
 * otherwise a standalone contact (e.g. imported via CSV).
 *
 * @property int $id
 * @property int $gym_id
 * @property string|null $contactable_type
 * @property int|null $contactable_id
 * @property string $phone
 * @property string|null $wa_id
 * @property string|null $name
 * @property string $opt_in_status
 * @property \Illuminate\Support\Carbon|null $opted_in_at
 * @property \Illuminate\Support\Carbon|null $opted_out_at
 * @property \Illuminate\Support\Carbon|null $last_inbound_at
 * @property string $source
 */
class WhatsappContact extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappContactFactory> */
    use BelongsToGym, HasFactory, SoftDeletes;

    protected $table = 'wa_contacts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'contactable_type',
        'contactable_id',
        'phone',
        'wa_id',
        'name',
        'opt_in_status',
        'opted_in_at',
        'opted_out_at',
        'last_inbound_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'last_inbound_at' => 'datetime',
        ];
    }

    /**
     * Whether this contact may currently be sent a free-form (non-template) message.
     *
     * Meta's customer service window: only pre-approved templates may be
     * sent once 24 hours have passed since the contact's last inbound
     * message.
     */
    public function isWithinServiceWindow(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->gt(now()->subHours(24));
    }

    public function isOptedIn(): bool
    {
        return $this->opt_in_status === 'opted_in';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WhatsappConversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    /**
     * @return BelongsToMany<WhatsappTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappTag::class, 'wa_contact_tag', 'wa_contact_id', 'wa_tag_id');
    }
}
