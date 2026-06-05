<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use App\Models\TarpaiPayment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CalculateWeeklyTarpaiCharges extends Command
{
    protected $signature = 'tarpai:calculate-weekly
                            {--week= : Any date within the target week (default: today). Format: YYYY-MM-DD}
                            {--triggered-by=Scheduler : Who triggered this run (Scheduler or Manual — User Name)}';

    protected $description = 'Calculate weekly Tarpai charges for Rashid Bhai and Yousaf Bhai based on pieces sent in the week (Saturday → Friday).';

    public function handle(): int
    {
        [$weekStart, $weekEnd] = $this->resolveWeekWindow();

        $this->info("Calculating Tarpai charges for week: {$weekStart->toDateString()} → {$weekEnd->toDateString()}");

        $created = 0;
        $updated = 0;
        $skipped = 0;

        try {
            $rows = DB::table('tarpai_sends as ts')
                ->join('tarpai_send_items as tsi', 'tsi.tarpai_send_id', '=', 'ts.id')
                ->whereIn('ts.tarpai_house', ['rashid_bhai', 'yousaf_bhai'])
                ->whereBetween('ts.sent_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->select(
                    'ts.catalogue_id',
                    'ts.tarpai_house',
                    DB::raw('SUM(tsi.quantity) as total_pieces'),
                    DB::raw('SUM(tsi.quantity * ts.per_piece_price) as total_amount')
                )
                ->groupBy('ts.catalogue_id', 'ts.tarpai_house')
                ->get();

            if ($rows->isEmpty()) {
                $output = 'No Tarpai sends found for Rashid Bhai or Yousaf Bhai in this week window. No records created.';
                $this->warn($output);

                CronLog::create([
                    'job_name'        => 'tarpai:calculate-weekly',
                    'job_label'       => 'Tarpai Charges',
                    'triggered_by'    => $this->option('triggered-by'),
                    'week_start'      => $weekStart->toDateString(),
                    'week_end'        => $weekEnd->toDateString(),
                    'records_created' => 0,
                    'records_updated' => 0,
                    'records_skipped' => 0,
                    'status'          => 'success',
                    'output'          => $output,
                    'ran_at'          => now(),
                ]);

                return self::SUCCESS;
            }

            foreach ($rows as $row) {
                $existing = TarpaiPayment::where('catalogue_id', $row->catalogue_id)
                    ->where('tarpai_house', $row->tarpai_house)
                    ->where('week_start', $weekStart->toDateString())
                    ->first();

                if ($existing) {
                    if ($existing->is_confirmed) {
                        $skipped++;
                        continue;
                    }

                    $existing->update([
                        'week_end'           => $weekEnd->toDateString(),
                        'total_pieces_sent'  => (int) $row->total_pieces,
                        'total_amount'       => (float) $row->total_amount,
                    ]);
                    $updated++;
                } else {
                    TarpaiPayment::create([
                        'catalogue_id'      => $row->catalogue_id,
                        'tarpai_house'      => $row->tarpai_house,
                        'week_start'        => $weekStart->toDateString(),
                        'week_end'          => $weekEnd->toDateString(),
                        'total_pieces_sent' => (int) $row->total_pieces,
                        'total_amount'      => (float) $row->total_amount,
                    ]);
                    $created++;
                }
            }

            $output = "Done. Created: {$created} | Updated: {$updated} | Skipped (confirmed): {$skipped}";
            $this->info($output);

            CronLog::create([
                'job_name'        => 'tarpai:calculate-weekly',
                'job_label'       => 'Tarpai Charges',
                'triggered_by'    => $this->option('triggered-by'),
                'week_start'      => $weekStart->toDateString(),
                'week_end'        => $weekEnd->toDateString(),
                'records_created' => $created,
                'records_updated' => $updated,
                'records_skipped' => $skipped,
                'status'          => 'success',
                'output'          => $output,
                'ran_at'          => now(),
            ]);

        } catch (Throwable $e) {
            $output = 'Error: ' . $e->getMessage();
            $this->error($output);

            CronLog::create([
                'job_name'        => 'tarpai:calculate-weekly',
                'job_label'       => 'Tarpai Charges',
                'triggered_by'    => $this->option('triggered-by'),
                'week_start'      => $weekStart->toDateString(),
                'week_end'        => $weekEnd->toDateString(),
                'records_created' => $created,
                'records_updated' => $updated,
                'records_skipped' => $skipped,
                'status'          => 'failed',
                'output'          => $output,
                'ran_at'          => now(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Return [weekStart, weekEnd] Carbon instances for the Saturday→Friday
     * window that contains the --week date (or today).
     */
    private function resolveWeekWindow(): array
    {
        $anchor = $this->option('week')
            ? Carbon::parse($this->option('week'))
            : Carbon::today();

        // Days since last Saturday: Sat=0, Sun=1, Mon=2, Tue=3, Wed=4, Thu=5, Fri=6
        $daysSinceSaturday = ($anchor->dayOfWeek + 1) % 7;
        $weekStart = $anchor->copy()->subDays($daysSinceSaturday)->startOfDay();
        $weekEnd   = $weekStart->copy()->addDays(6)->endOfDay();

        return [$weekStart, $weekEnd];
    }
}
