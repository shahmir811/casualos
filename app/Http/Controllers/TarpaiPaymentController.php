<?php

namespace App\Http\Controllers;

use App\Models\TarpaiPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TarpaiPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = TarpaiPayment::with(['catalogue', 'confirmedBy']);

        // Filter by week — resolve any date in the week to its Saturday week_start
        if ($request->filled('week_date')) {
            $anchor       = Carbon::parse($request->input('week_date'));
            $daysSinceSat = ($anchor->dayOfWeek + 1) % 7;
            $weekStart    = $anchor->copy()->subDays($daysSinceSat)->toDateString();
            $query->where('week_start', $weekStart);
        }

        if ($request->filled('tarpai_house')) {
            $query->where('tarpai_house', $request->input('tarpai_house'));
        }

        if ($request->input('status') === 'pending') {
            $query->where('is_confirmed', false);
        } elseif ($request->input('status') === 'confirmed') {
            $query->where('is_confirmed', true);
        }

        $payments = $query->latest('week_start')->paginate(20)->withQueryString();

        return view('analytics.tarpai-charges.index', compact('payments'));
    }

    public function show(TarpaiPayment $tarpaiPayment)
    {
        $tarpaiPayment->load(['catalogue', 'confirmedBy']);

        // Load each individual TarpaiSend that falls within this week for this house/catalogue,
        // along with its summed pieces and computed cost.
        $sends = DB::table('tarpai_sends as ts')
            ->join('tarpai_send_items as tsi', 'tsi.tarpai_send_id', '=', 'ts.id')
            ->where('ts.catalogue_id', $tarpaiPayment->catalogue_id)
            ->where('ts.tarpai_house', $tarpaiPayment->tarpai_house)
            ->whereBetween('ts.sent_date', [
                $tarpaiPayment->week_start->toDateString(),
                $tarpaiPayment->week_end->toDateString(),
            ])
            ->select(
                'ts.id',
                'ts.sent_date',
                'ts.per_piece_price',
                DB::raw('SUM(tsi.quantity) as pieces'),
                DB::raw('SUM(tsi.quantity * ts.per_piece_price) as amount')
            )
            ->groupBy('ts.id', 'ts.sent_date', 'ts.per_piece_price')
            ->orderBy('ts.sent_date')
            ->get();

        return view('analytics.tarpai-charges.show', compact('tarpaiPayment', 'sends'));
    }

    public function confirm(TarpaiPayment $tarpaiPayment)
    {
        $this->denyCreativeHead();
        $tarpaiPayment->loadMissing('catalogue');
        $tarpaiPayment->update([
            'is_confirmed' => true,
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        activity()
            ->performedOn($tarpaiPayment)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'catalogue'      => $tarpaiPayment->catalogue?->name ?? '—',
                'tarpai_house'   => $tarpaiPayment->houseLabel(),
                'week'           => $tarpaiPayment->week_start->format('d M Y') . ' – ' . $tarpaiPayment->week_end->format('d M Y'),
                'total_pieces'   => $tarpaiPayment->total_pieces_sent,
                'total_amount'   => 'PKR ' . number_format((float) $tarpaiPayment->total_amount, 0),
                'confirmed_by'   => Auth::user()->name,
            ])
            ->log('Tarpai payment confirmed for ' . $tarpaiPayment->houseLabel() . ' — week of ' . $tarpaiPayment->week_start->format('d M Y'));

        return back()->with('success', 'Payment confirmed for ' . $tarpaiPayment->houseLabel() . ' — week of ' . $tarpaiPayment->week_start->format('d M') . '.');
    }

    public function recalculate(Request $request)
    {
        $this->denyCreativeHead();
        $request->validate([
            'week_date' => 'required|date',
        ]);

        Artisan::call('tarpai:calculate-weekly', [
            '--week'         => $request->input('week_date'),
            '--triggered-by' => 'Manual — ' . Auth::user()->name,
        ]);

        return back()->with('success', 'Tarpai charges recalculated for the week containing ' . Carbon::parse($request->input('week_date'))->format('d M Y') . '. Confirmed records were not changed.');
    }
}
