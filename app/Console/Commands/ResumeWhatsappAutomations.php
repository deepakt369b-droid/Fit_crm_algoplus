<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatsappAutomationRun;
use App\Models\WhatsappAutomationRun;
use Illuminate\Console\Command;

/**
 * Resumes automation runs whose `wait` step has elapsed.
 *
 * A `wait` step can be hours or days long, which is unreliable to model
 * as a queue job delay across every queue driver/infra combination this
 * app might run on - this scheduled sweep (see routes/console.php) is
 * the more robust alternative: it just re-dispatches the processing job
 * for anything due, and the job itself picks up exactly where it left
 * off via current_step_index.
 */
class ResumeWhatsappAutomations extends Command
{
    /**
     * @var string
     */
    protected $signature = 'fitcrm:automations:resume';

    /**
     * @var string
     */
    protected $description = 'Resume WhatsApp automation runs whose wait step has elapsed';

    public function handle(): int
    {
        $dueRuns = WhatsappAutomationRun::query()
            ->where('status', 'waiting')
            ->whereNotNull('resume_at')
            ->where('resume_at', '<=', now())
            ->get();

        foreach ($dueRuns as $run) {
            $run->forceFill(['status' => 'running'])->save();
            ProcessWhatsappAutomationRun::dispatch($run->id);
        }

        $this->info("{$dueRuns->count()} automation run(s) resumed.");

        return self::SUCCESS;
    }
}
