<?php

namespace App\Http\Controllers;

use App\Exports\BankCollectionExport;
use App\Models\BankAccount;
use App\Models\Catalogue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class BankCollectionReportController extends Controller
{
    public function index()
    {
        $catalogueId       = session('active_catalogue_id');
        $selectedCatalogue = $catalogueId ? Catalogue::find($catalogueId) : null;
        $banks             = BankAccount::orderBy('id')->get();

        if (!$selectedCatalogue) {
            return view('reports.bank-collection', [
                'selectedCatalogue' => null,
                'banks'             => $banks,
                'orderCounts'       => [],
                'collected'         => [],
                'expected'          => [],
                'receivable'        => [],
                'miscAmount'        => 0,
                'grandCollected'    => 0,
                'grandExpected'     => 0,
                'grandReceivable'   => 0,
                'rows'              => [],
            ]);
        }

        $data = $this->loadData((int) $catalogueId, $banks);

        return view('reports.bank-collection', array_merge(
            ['selectedCatalogue' => $selectedCatalogue, 'banks' => $banks],
            $data
        ));
    }

    public function pdf()
    {
        $catalogueId       = (int) session('active_catalogue_id');
        $selectedCatalogue = Catalogue::findOrFail($catalogueId);
        $banks             = BankAccount::orderBy('id')->get();
        $data              = $this->loadData($catalogueId, $banks);
        $logoDataUri       = pdf_logo_data_uri();

        return Pdf::loadView('reports.bank-collection-pdf', array_merge(
            ['selectedCatalogue' => $selectedCatalogue, 'banks' => $banks, 'logoDataUri' => $logoDataUri],
            $data
        ))
            ->setPaper('a4', 'landscape')
            ->download('bank-collection-' . str($selectedCatalogue->name)->slug() . '.pdf');
    }

    public function excel()
    {
        $catalogueId       = (int) session('active_catalogue_id');
        $selectedCatalogue = Catalogue::findOrFail($catalogueId);
        $banks             = BankAccount::orderBy('id')->get();
        $data              = $this->loadData($catalogueId, $banks);

        return (new BankCollectionExport(
            banks:             $banks,
            rows:              $data['rows'],
            collected:         $data['collected'],
            expected:          $data['expected'],
            receivable:        $data['receivable'],
            miscAmount:        $data['miscAmount'],
            grandCollected:    $data['grandCollected'],
            grandExpected:     $data['grandExpected'],
            grandReceivable:   $data['grandReceivable'],
            selectedCatalogue: $selectedCatalogue,
        ))->download('bank-collection-' . str($selectedCatalogue->name)->slug() . '.xlsx');
    }

    private function loadData(int $catalogueId, $banks): array
    {
        $collected   = [];
        $expected    = [];
        $receivable  = [];
        $orderCounts = [];

        foreach ($banks as $bank) {
            $orderCounts[$bank->id] = (int) DB::table('orders')
                ->where('catalogue_id', $catalogueId)
                ->where('assigned_bank_account_id', $bank->id)
                ->whereNotIn('status', ['cancelled'])
                ->count();

            $collected[$bank->id] = (float) DB::table('payments')
                ->join('orders', 'payments.order_id', '=', 'orders.id')
                ->where('orders.catalogue_id', $catalogueId)
                ->where('payments.bank_account_id', $bank->id)
                ->sum('payments.amount');

            $expected[$bank->id] = (float) DB::table('orders')
                ->where('catalogue_id', $catalogueId)
                ->where('assigned_bank_account_id', $bank->id)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');

            $receivable[$bank->id] = (float) DB::table('orders')
                ->where('catalogue_id', $catalogueId)
                ->where('assigned_bank_account_id', $bank->id)
                ->whereNotIn('status', ['cancelled'])
                ->sum('outstanding_balance');
        }

        $miscAmount = (float) DB::table('customer_ledger')
            ->join('orders', function ($join) {
                $join->on('customer_ledger.reference_id', '=', 'orders.id')
                     ->where('customer_ledger.reference_type', 'App\\Models\\Order');
            })
            ->where('orders.catalogue_id', $catalogueId)
            ->where('customer_ledger.transaction_type', 'credit_applied')
            ->sum(DB::raw('ABS(customer_ledger.amount)'));

        $grandCollected = (float) DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.catalogue_id', $catalogueId)
            ->sum('payments.amount') + $miscAmount;

        $grandExpected = (float) DB::table('orders')
            ->where('catalogue_id', $catalogueId)
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');

        $grandReceivable = (float) DB::table('orders')
            ->where('catalogue_id', $catalogueId)
            ->whereNotIn('status', ['cancelled'])
            ->sum('outstanding_balance');

        // Per-order rows
        $allOrders = DB::table('orders')
            ->where('orders.catalogue_id', $catalogueId)
            ->whereNotIn('orders.status', ['cancelled'])
            ->leftJoin('bank_accounts as ab', 'orders.assigned_bank_account_id', '=', 'ab.id')
            ->select(
                'orders.id',
                'orders.submitted_name',
                'orders.submitted_city',
                'orders.total_amount',
                'orders.total_paid',
                'orders.outstanding_balance',
                DB::raw('ab.title as assigned_bank_title')
            )
            ->orderBy('orders.id')
            ->get();

        $rows = [];

        if ($allOrders->isNotEmpty()) {
            $orderIds = $allOrders->pluck('id')->all();

            // First order_item per order for size breakdown (all designs share same qty per size)
            $firstItemByOrder = DB::table('order_items')
                ->whereIn('order_id', $orderIds)
                ->orderBy('order_id')
                ->orderBy('design_id')
                ->get()
                ->groupBy('order_id')
                ->map(fn($items) => $items->first());

            // Over-all qty per order = sum of total_qty across all designs
            $overAllQtyByOrder = DB::table('order_items')
                ->whereIn('order_id', $orderIds)
                ->selectRaw('order_id, SUM(total_qty) as over_all_qty')
                ->groupBy('order_id')
                ->pluck('over_all_qty', 'order_id');

            // Bank transfer payments per order per bank
            $bankPaymentRows = DB::table('payments')
                ->whereIn('order_id', $orderIds)
                ->whereNotNull('bank_account_id')
                ->selectRaw('order_id, bank_account_id, SUM(amount) as total')
                ->groupBy('order_id', 'bank_account_id')
                ->get();

            $bankPaymentMap = [];
            foreach ($bankPaymentRows as $row) {
                $bankPaymentMap[$row->order_id][$row->bank_account_id] = (float) $row->total;
            }

            foreach ($allOrders as $order) {
                $firstItem  = $firstItemByOrder->get($order->id);
                $qtyXs      = $firstItem ? (int) $firstItem->qty_xs : 0;
                $qtyS       = $firstItem ? (int) $firstItem->qty_s  : 0;
                $qtyM       = $firstItem ? (int) $firstItem->qty_m  : 0;
                $qtyL       = $firstItem ? (int) $firstItem->qty_l  : 0;
                $qtyXl      = $firstItem ? (int) $firstItem->qty_xl : 0;
                $overAllQty = (int) ($overAllQtyByOrder[$order->id] ?? 0);
                $totalQty   = $qtyXs + $qtyS + $qtyM + $qtyL + $qtyXl;
                $rate       = $overAllQty > 0 ? (int) round($order->total_amount / $overAllQty) : 0;

                $bankPayments   = [];
                $totalBankPaid  = 0.0;
                foreach ($banks as $bank) {
                    $amt = $bankPaymentMap[$order->id][$bank->id] ?? 0.0;
                    $bankPayments[$bank->id] = $amt;
                    $totalBankPaid += $amt;
                }

                // Misc = anything paid not via a specific bank (cash + advance credits)
                $misc = max(0.0, (float) $order->total_paid - $totalBankPaid);

                $rows[] = [
                    'name'              => $order->submitted_name,
                    'city'              => $order->submitted_city ?? '',
                    'qty_xs'            => $qtyXs,
                    'qty_s'             => $qtyS,
                    'qty_m'             => $qtyM,
                    'qty_l'             => $qtyL,
                    'qty_xl'            => $qtyXl,
                    'total_qty'         => $totalQty,
                    'over_all_qty'      => $overAllQty,
                    'rate'              => $rate,
                    'total_bill'        => (float) $order->total_amount,
                    'amount_received'   => (float) $order->total_paid,
                    'amount_receivable' => (float) $order->outstanding_balance,
                    'title_given'       => $order->assigned_bank_title ?? '',
                    'bank_payments'     => $bankPayments,
                    'misc'              => $misc,
                ];
            }
        }

        return compact(
            'collected', 'expected', 'receivable', 'orderCounts',
            'miscAmount', 'grandCollected', 'grandExpected', 'grandReceivable',
            'rows'
        );
    }
}
