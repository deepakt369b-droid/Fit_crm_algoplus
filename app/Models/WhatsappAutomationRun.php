<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One contact's progress through one automation.
 *
 * @property int $id
 * @property int $wa_automation_id
 * @property int $wa_contact_id
 * @property string $status
 * @property int $current_step_index
 * @property array<string, mixed>|null $context
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $resume_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class WhatsappAutomationRun extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappAutomationRunFactory> */
    use HasFactory;

    protected $table = 'wa_automation_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'wa_automation_id',
        'wa_contact_id',
        'status',
        'current_step_index',
        'context',
        'error_message',
        'resume_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resume_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WhatsappAutomation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(WhatsappAutomation::class, 'wa_automation_id');
    }

    /**
     * @return BelongsTo<WhatsappContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'wa_contact_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }
}
