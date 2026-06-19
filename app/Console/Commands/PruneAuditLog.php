<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PruneAuditLog extends Command
{
    protected $signature = 'audit-log:prune';

    protected $description = 'Delete activity_log entries older than 45 days.';

    public function handle(): int
    {
        $cutoff = Carbon::today()->subDays(45);

        $this->info("Pruning audit log entries older than {$cutoff->toDateString()}.");

        try {
            $deleted = DB::table('activity_log')
                ->where('created_at', '<', $cutoff)
                ->delete();

            $noun   = $deleted === 1 ? 'entry' : 'entries';
            $output = "Deleted {$deleted} audit log {$noun} older than {$cutoff->toDateString()}.";
            $this->info($output);

            CronLog::create([
                'job_name'        => 'audit-log:prune',
                'job_label'       => 'Audit Log Pruning',
                'triggered_by'    => 'Scheduler',
                'week_start'      => null,
                'week_end'        => null,
                'records_created' => 0,
                'records_updated' => 0,
                'records_skipped' => 0,
                'status'          => 'success',
                'output'          => $output,
                'ran_at'          => now(),
            ]);

        } catch (Throwable $e) {
            $output = 'Error: ' . $e->getMessage();
            $this->error($output);

            CronLog::create([
                'job_name'        => 'audit-log:prune',
                'job_label'       => 'Audit Log Pruning',
                'triggered_by'    => 'Scheduler',
                'week_start'      => null,
                'week_end'        => null,
                'records_created' => 0,
                'records_updated' => 0,
                'records_skipped' => 0,
                'status'          => 'failed',
                'output'          => $output,
                'ran_at'          => now(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
