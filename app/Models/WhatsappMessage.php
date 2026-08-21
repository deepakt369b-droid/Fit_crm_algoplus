<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $wa_conversation_id
 * @property string $direction
 * @property string $type
 * @property string|null $meta_message_id
 * @property string $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $body
 * @property string|null $template_name
 * @property string|null $media_url
 * @property int|null $sent_by
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class WhatsappMessage extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappMessageFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'wa_conversation_id',
        'direction',
        'type',
        'meta_message_id',
        'status',
        'error_code',
        'error_message',
        'body',
        'template_name',
        'media_url',
        'sent_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WhatsappConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'wa_conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Propagate a status change to this message's broadcast recipient
     * row (if it belongs to one) and the parent broadcast's counters,
     * rather than tracking delivered/read separately in two places.
     */
    protected static function booted(): void
    {
        static::updated(function (self $message): void {
            if (! $message->wasChanged('status')) {
                return;
            }

            $recipient = WhatsappBroadcastRecipient::query()
                ->where('wa_message_id', $message->id)
                ->first();

            if ($recipient === null) {
                return;
            }

            $recipient->forceFill(['status' => $message->status])->saveQuietly();

            $column = match ($message->status) {
                'delivered' => 'delivered_count',
                'read' => 'read_count',
                'failed' => 'failed_count',
                default => null,
            };

            if ($column !== null) {
                $recipient->broadcast?->increment($column);
            }
        });
    }
}
