<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recipient's send outcome within a broadcast. Tracks only the send
 * attempt itself (pending/sent/failed/skipped/throttled); once sent, the
 * linked WhatsappMessage is the source of truth for delivered/read —
 * WhatsappMessage propagates status changes back here (see its
 * booted() hook) rather than duplicating that tracking.
 *
 * @property int $id
 * @property int $wa_broadcast_id
 * @property int $wa_contact_id
 * @property int|null $wa_message_id
 * @property string $status
 * @property list<string>|null $variables
 * @property string|null $error_message
 */
class WhatsappBroadcastRecipient extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappBroadcastRecipientFactory> */
    use HasFactory;

    protected $table = 'wa_broadcast_recipients';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'wa_broadcast_id',
        'wa_contact_id',
        'wa_message_id',
        'status',
        'variables',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WhatsappBroadcast, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WhatsappBroadcast::class, 'wa_broadcast_id');
    }

    /**
     * @return BelongsTo<WhatsappContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'wa_contact_id');
    }

    /**
     * @return BelongsTo<WhatsappMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'wa_message_id');
    }
}
