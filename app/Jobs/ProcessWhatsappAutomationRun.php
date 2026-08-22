<?php

namespace App\Jobs;

use App\Models\WhatsappAutomationRun;
use App\Services\WhatsApp\AutomationStepExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Advances one automation run: executes steps in sequence, following
 * `condition` jumps, until it hits a `wait` step, runs out of steps, or
 * fails. A `wait` step ends this job's execution entirely - a scheduled
 * command (fitcrm:automations:resume) picks waiting runs back up when
 * their resume_at has passed, rather than relying on a queue delay that
 * could be days long.
 *
 * MAX_STEPS_PER_RUN is the loop-safety net: a misconfigured `condition`
 * step whose branches point back at each other would otherwise run
 * forever. Hitting the cap fails the run with a clear error instead of
 * consuming queue workers indefinitely.
 */
class ProcessWhatsappAutomationRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_STEPS_PER_INVOCATION = 200;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $runId) {}

    public function handle(AutomationStepExecutor $executor): void
    {
        $run = WhatsappAutomationRun::query()->find($this->runId);

        if ($run === null || $run->isFinished()) {
            return;
        }

        $automation = $run->automation;

        if ($automation === null || ! $automation->isActive()) {
            $run->forceFill([
                'status' => 'failed',
                'error_message' => 'Automation is missing or inactive.',
                'completed_at' => now(),
            ])->save();

            return;
        }

        if ($run->started_at === null) {
            $run->forceFill(['started_at' => now(), 'status' => 'running'])->save();
        }

        $steps = $automation->steps;

        for ($i = 0; $i < self::MAX_STEPS_PER_INVOCATION; $i++) {
            $step = $steps[$run->current_step_index] ?? null;

            if ($step === null) {
                $run->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

                return;
            }

            $outcome = $executor->execute($automation, $run, $step);

            match ($outcome['action']) {
                'advance' => $run->update(['current_step_index' => $run->current_step_index + 1]),
                'jump' => $run->update(['current_step_index' => $outcome['step_index']]),
                'wait' => $this->beginWait($run, $outcome['minutes']),
                'fail' => $this->fail($run, $outcome['error'] ?? 'Automation step failed.'),
                default => $this->fail($run, 'Unknown step outcome.'),
            };

            if (in_array($outcome['action'], ['wait', 'fail'], true)) {
                return;
            }
        }

        $this->fail($run, 'Automation exceeded '.self::MAX_STEPS_PER_INVOCATION.' steps in one run - likely a condition loop.');
    }

    private function beginWait(WhatsappAutomationRun $run, int $minutes): void
    {
        $run->forceFill([
            'status' => 'waiting',
            'resume_at' => now()->addMinutes($minutes),
            'current_step_index' => $run->current_step_index + 1,
        ])->save();
    }

    private function fail(WhatsappAutomationRun $run, string $message): void
    {
        $run->forceFill([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ])->save();
    }
}
