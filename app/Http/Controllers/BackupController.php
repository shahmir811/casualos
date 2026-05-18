<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * BackupController — Admin only.
 *
 * Creates pure-PHP MySQL dumps via PDO (no mysqldump binary needed).
 * Stores .sql files under backups/ on the configured filesystem disk.
 */
class BackupController extends Controller
{
    protected string $backupDir = 'backups';

    /* ------------------------------------------------------------------ */
    /*  INDEX — list all backup files                                       */
    /* ------------------------------------------------------------------ */
    public function index()
    {
        $files = collect(Storage::files($this->backupDir))
            ->filter(fn($f) => str_ends_with($f, '.sql'))
            ->map(function ($path) {
                $name = basename($path);
                $size = Storage::size($path);
                $time = Storage::lastModified($path);

                return (object) [
                    'name'       => $name,
                    'path'       => $path,
                    'size_human' => $this->humanSize($size),
                    'created_at' => \Carbon\Carbon::createFromTimestamp($time, config('app.timezone')),
                ];
            })
            ->sortByDesc(fn($f) => $f->created_at)
            ->values();

        return view('backups.index', compact('files'));
    }

    /* ------------------------------------------------------------------ */
    /*  STORE — generate a new SQL dump via PHP/PDO                        */
    /* ------------------------------------------------------------------ */
    public function store()
    {
        $filename = 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql';
        $path     = $this->backupDir . '/' . $filename;

        try {
            $sql = $this->generateSqlDump();
            Storage::put($path, $sql);

            activity()
                ->causedBy(auth()->user())
                ->log("Database backup created: {$filename} (" . $this->humanSize(strlen($sql)) . ')');

            return back()->with('success', "Backup created successfully: {$filename}");

        } catch (\Throwable $e) {
            Storage::delete($path);

            activity()
                ->causedBy(auth()->user())
                ->log('Database backup FAILED: ' . $e->getMessage());

            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /*  DOWNLOAD                                                            */
    /* ------------------------------------------------------------------ */
    public function download(string $filename)
    {
        $path = $this->backupDir . '/' . basename($filename);

        if (!Storage::exists($path)) {
            abort(404, 'Backup file not found.');
        }

        activity()
            ->causedBy(auth()->user())
            ->log("Downloaded backup: {$filename}");

        return response()->streamDownload(
            fn () => print(Storage::get($path)),
            $filename
        );
    }

    /* ------------------------------------------------------------------ */
    /*  DESTROY                                                             */
    /* ------------------------------------------------------------------ */
    public function destroy(string $filename)
    {
        $path = $this->backupDir . '/' . basename($filename);

        if (!Storage::exists($path)) {
            return back()->with('error', 'Backup file not found.');
        }

        Storage::delete($path);

        activity()
            ->causedBy(auth()->user())
            ->log("Deleted backup: {$filename}");

        return back()->with('success', "Backup deleted: {$filename}");
    }

    /* ------------------------------------------------------------------ */
    /*  CORE: pure-PHP SQL dump generator                                  */
    /* ------------------------------------------------------------------ */
    public function generateSqlDump(): string
    {
        $db     = config('database.connections.mysql.database');
        $pdo    = DB::getPdo();

        $output  = [];
        $output[] = '-- CasualiteOS Database Backup';
        $output[] = '-- Generated: ' . now()->toDateTimeString();
        $output[] = '-- Database:  ' . $db;
        $output[] = '';
        $output[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $output[] = 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";';
        $output[] = 'SET NAMES utf8mb4;';
        $output[] = '';

        // Get all tables
        $tables = $pdo->query("SHOW TABLES FROM `{$db}`")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // ── CREATE TABLE ──────────────────────────────────────────
            $output[] = "-- ----------------------------";
            $output[] = "-- Table structure for `{$table}`";
            $output[] = "-- ----------------------------";
            $output[] = "DROP TABLE IF EXISTS `{$table}`;";

            $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
            $output[] = $createRow['Create Table'] . ';';
            $output[] = '';

            // ── ROWS ──────────────────────────────────────────────────
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $output[] = "-- Data for `{$table}`";

                // Get column names for INSERT
                $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';

                // Chunk INSERTs into groups of 100 rows
                foreach (array_chunk($rows, 100) as $chunk) {
                    $values = array_map(function ($row) use ($pdo) {
                        $escaped = array_map(function ($val) use ($pdo) {
                            if ($val === null) return 'NULL';
                            return $pdo->quote($val);
                        }, array_values($row));
                        return '(' . implode(', ', $escaped) . ')';
                    }, $chunk);

                    $output[] = "INSERT INTO `{$table}` ({$columns}) VALUES";
                    $output[] = implode(",\n", $values) . ';';
                }

                $output[] = '';
            }
        }

        $output[] = '';
        $output[] = 'SET FOREIGN_KEY_CHECKS=1;';
        $output[] = '-- End of backup';

        return implode("\n", $output);
    }

    /* ------------------------------------------------------------------ */
    /*  HELPERS                                                             */
    /* ------------------------------------------------------------------ */
    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 2) . ' MB';
        if ($bytes >= 1_024)     return round($bytes / 1_024, 1)     . ' KB';
        return $bytes . ' B';
    }
}
