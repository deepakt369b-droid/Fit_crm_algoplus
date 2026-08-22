<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A trigger + ordered list of steps run automatically for a contact.
 * See the wa_automations migration for the step JSON contract, and
 * AutomationStepExecutor for how each step type is actually run.
 *
 * @property int $id
 * @property int|null $gym_id
 * @property int|null $wa_phone_number_id
 * @property string $name
 * @property string $trigger_type
 * @property array<string, mixed>|null $trigger_config
 * @property list<array<string, mixed>> $steps
 * @property string $status
 * @property int|null $created_by
 */
class WhatsappAutomation extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappAutomationFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_automations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'wa_phone_number_id',
        'name',
        'trigger_type',
        'trigger_config',
        'steps',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'steps' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $automation): void {
            if ($automation->created_by === null && auth()->check()) {
                $automation->created_by = auth()->id();
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WhatsappAutomationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(WhatsappAutomationRun::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
