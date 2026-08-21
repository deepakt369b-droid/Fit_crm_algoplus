<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A local cache of one Meta-approved WhatsApp message template.
 *
 * @property int $id
 * @property int|null $gym_id
 * @property int $wa_phone_number_id
 * @property string|null $meta_template_id
 * @property string $name
 * @property string $language
 * @property string|null $category
 * @property string $status
 * @property array<string, mixed>|null $components
 */
class WhatsappTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappTemplateFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_templates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'wa_phone_number_id',
        'meta_template_id',
        'name',
        'language',
        'category',
        'status',
        'components',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WhatsappPhoneNumber, $this>
     */
    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'wa_phone_number_id');
    }
}
