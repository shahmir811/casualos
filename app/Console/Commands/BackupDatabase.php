<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackupController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * BackupDatabase — Artisan command for automated/manual DB backups.
 *
 * Usage:
 *   php artisan backup:database
 *
 * Scheduled to run automatically every day at midnight (see routes/console.php).
 * Also auto-prunes backups older than 30 days to keep storage clean.
 */
class BackupDatabase extends Command
{
    protected $signature   = 'backup:database {--prune=30 : Delete backups older than N days (0 = keep all)}';
    protected $description = 'Create a full database backup and optionally prune old ones';

    public function handle(BackupController $backupCtrl): int
    {
        $this->info('CasualOS — Database Backup');
        $this->line('──────────────────────────');

        // ── Create backup ──────────────────────────────────────────────
        $backupDir   = 'backups';
        $filename    = 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql';
        $storagePath = storage_path('app/' . $backupDir . '/' . $filename);

        Storage::disk('local')->makeDirectory($backupDir);

        $this->line("Generating SQL dump...");

        try {
            $sql = $backupCtrl->generateSqlDump();
            file_put_contents($storagePath, $sql);

            $size = $this->humanSize(strlen($sql));
            $this->info("✓ Backup created: {$filename} ({$size})");

            activity()
                ->log("Scheduled backup created: {$filename} ({$size})");

        } catch (\Throwable $e) {
            @unlink($storagePath);
            $this->error("✗ Backup FAILED: " . $e->getMessage());

            activity()
                ->log("Scheduled backup FAILED: " . $e->getMessage());

            return self::FAILURE;
        }

        // ── Prune old backups ──────────────────────────────────────────
        $pruneDays = (int) $this->option('prune');

        if ($pruneDays > 0) {
            $this->line("Pruning backups older than {$pruneDays} days...");
            $pruned = 0;

            $files = Storage::disk('local')->files($backupDir);

            foreach ($files as $path) {
                if (!str_ends_with($path, '.sql')) continue;
                $modified = Storage::disk('local')->lastModified($path);

                if (now()->diffInDays(\Carbon\Carbon::createFromTimestamp($modified)) >= $pruneDays) {
                    Storage::disk('local')->delete($path);
                    $pruned++;
                    $this->line("  Deleted: " . basename($path));
                }
            }

            if ($pruned === 0) {
                $this->line("  No old backups to prune.");
            } else {
                $this->info("  ✓ Pruned {$pruned} old backup(s).");
            }
        }

        $this->line('──────────────────────────');
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 2) . ' MB';
        if ($bytes >= 1_024)     return round($bytes / 1_024, 1)     . ' KB';
        return $bytes . ' B';
    }
}
