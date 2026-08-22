<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A branch's AI reply-assistant configuration - one row per gym.
 *
 * @property int $id
 * @property int|null $gym_id
 * @property string|null $anthropic_api_key
 * @property string $model
 * @property string|null $system_prompt
 */
class WhatsappAiSetting extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappAiSettingFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_ai_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'anthropic_api_key',
        'model',
        'system_prompt',
    ];

    protected function casts(): array
    {
        return [
            'anthropic_api_key' => 'encrypted',
        ];
    }
}
