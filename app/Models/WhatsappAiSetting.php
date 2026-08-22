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
 * @property string $provider
 * @property string|null $api_key
 * @property string|null $base_url
 * @property string|null $anthropic_api_key deprecated - superseded by $api_key/$provider; column kept (unused by application code) rather than dropped, since dropping a column is a destructive migration
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
        'provider',
        'api_key',
        'base_url',
        'model',
        'system_prompt',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }
}
