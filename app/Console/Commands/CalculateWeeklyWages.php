<?php

namespace App\Console\Commands;

use App\Models\Wage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateWeeklyWages extends Command
{
    protected $signature = 'wages:calculate-weekly
                            {--week= : Any date within the target week (default: today). Format: YYYY-MM-DD}';

    protected $description = 'Calculate wages for all per-piece stitching units based on kameez returned in the week (Saturday → Friday).';

    public function handle(): int
    {
        [$weekStart, $weekEnd] = $this->resolveWeekWindow();

        $this->info("Calculating wages for week: {$weekStart->toDateString()} → {$weekEnd->toDateString()}");

        // Sum kameez returned per catalogue per stitching unit in the week window.
        $rows = DB::table('stitching_return_items as sri')
            ->join('stitching_returns as sr', 'sr.id', '=', 'sri.stitching_return_id')
            ->join('stitching_units as su', 'su.id', '=', 'sr.stitching_unit_id')
            ->where('sri.component', 'kameez')
            ->where('su.payment_type', 'per_piece')
            ->whereBetween('sr.return_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->whereNotNull('sr.stitching_unit_id')
            ->select(
                'sr.catalogue_id',
                'sr.stitching_unit_id',
                'su.per_piece_rate',
                DB::raw('SUM(sri.quantity) as kameez_count')
            )
            ->groupBy('sr.catalogue_id', 'sr.stitching_unit_id', 'su.per_piece_rate')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No kameez returns found in this week window. No wage records created.');
            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $rate  = $row->per_piece_rate ?? 0;
            $total = $row->kameez_count * $rate;

            $existing = Wage::where('catalogue_id', $row->catalogue_id)
                ->where('stitching_unit_id', $row->stitching_unit_id)
                ->where('week_start', $weekStart->toDateString())
                ->first();

            if ($existing) {
                if ($existing->is_confirmed) {
                    $skipped++;
                    continue;
                }

                $existing->update([
                    'week_end'             => $weekEnd->toDateString(),
                    'total_suits_stitched' => $row->kameez_count,
                    'wage_rate'            => $rate,
                    // total_wages is recomputed in Wage::booted() saving hook
                ]);
                $updated++;
            } else {
                Wage::create([
                    'catalogue_id'         => $row->catalogue_id,
                    'stitching_unit_id'    => $row->stitching_unit_id,
                    'week_start'           => $weekStart->toDateString(),
                    'week_end'             => $weekEnd->toDateString(),
                    'total_suits_stitched' => $row->kameez_count,
                    'wage_rate'            => $rate,
                ]);
                $created++;
            }
        }

        $this->info("Done. Created: {$created} | Updated: {$updated} | Skipped (confirmed): {$skipped}");

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
