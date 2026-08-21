<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A WhatsApp Business phone number connected via the Meta Cloud API.
 *
 * @property int $id
 * @property int|null $gym_id
 * @property string $waba_id
 * @property string $phone_number_id
 * @property string|null $display_phone_number
 * @property string|null $verified_name
 * @property string|null $access_token
 * @property bool $is_shared
 * @property int $messaging_tier_limit
 * @property string $status
 */
class WhatsappPhoneNumber extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappPhoneNumberFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_phone_numbers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'waba_id',
        'phone_number_id',
        'display_phone_number',
        'verified_name',
        'access_token',
        'is_shared',
        'messaging_tier_limit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'is_shared' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WhatsappTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(WhatsappTemplate::class);
    }

    /**
     * @return HasMany<WhatsappConversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    /**
     * @return HasMany<WhatsappBroadcast, $this>
     */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(WhatsappBroadcast::class);
    }

    /**
     * Unique contacts this number has proactively messaged (via a
     * broadcast) in the last rolling 24 hours — the thing Meta's
     * messaging tier actually caps. Service replies within an existing
     * conversation don't count against this, so only broadcast sends are
     * counted here, not every wa_message.
     */
    public function newConversationsInLast24Hours(): int
    {
        return WhatsappBroadcastRecipient::query()
            ->whereHas('broadcast', fn ($query) => $query->where('wa_phone_number_id', $this->id))
            ->whereIn('status', ['sent', 'delivered', 'read', 'failed'])
            ->where('updated_at', '>=', now()->subDay())
            ->distinct('wa_contact_id')
            ->count('wa_contact_id');
    }

    public function remainingMessagingCapacity(): int
    {
        return max(0, $this->messaging_tier_limit - $this->newConversationsInLast24Hours());
    }
}
