<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneBackups extends Command
{
    protected $signature = 'backups:prune';

    protected $description = 'Delete S3 backup files older than 30 days.';

    public function handle(): int
    {
        $cutoff    = Carbon::today()->subDays(30);
        $backupDir = 'backups';

        $this->info("Pruning backup files older than {$cutoff->toDateString()}.");

        $deleted = 0;

        try {
            $disk  = Storage::disk('s3');
            $files = $disk->files($backupDir);

            foreach ($files as $filePath) {
                if (!str_ends_with($filePath, '.sql')) {
                    continue;
                }

                $modified = Carbon::createFromTimestamp($disk->lastModified($filePath));

                if ($modified->lt($cutoff)) {
                    $disk->delete($filePath);
                    $deleted++;
                    $this->line('  Deleted: ' . basename($filePath));
                }
            }

            $noun   = $deleted === 1 ? 'file' : 'files';
            $output = $deleted > 0
                ? "Deleted {$deleted} backup {$noun} older than {$cutoff->toDateString()}."
                : "No backup files older than {$cutoff->toDateString()} found. Nothing deleted.";

            $this->info($output);

            CronLog::create([
                'job_name'        => 'backups:prune',
                'job_label'       => 'Backup Pruning',
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
                'job_name'        => 'backups:prune',
                'job_label'       => 'Backup Pruning',
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
