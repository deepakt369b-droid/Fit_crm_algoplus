<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $wa_phone_number_id
 * @property int $wa_contact_id
 * @property string $status
 * @property int|null $assigned_user_id
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property \Illuminate\Support\Carbon|null $last_inbound_at
 * @property int $unread_count
 */
class WhatsappConversation extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappConversationFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_conversations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'wa_phone_number_id',
        'wa_contact_id',
        'status',
        'assigned_user_id',
        'last_message_at',
        'last_inbound_at',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WhatsappPhoneNumber, $this>
     */
    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'wa_phone_number_id');
    }

    /**
     * @return BelongsTo<WhatsappContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'wa_contact_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return HasMany<WhatsappMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
