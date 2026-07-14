<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Order;
use App\Models\OrderReduction;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function show(Customer $customer)
    {
        $entries = CustomerLedger::where('customer_id', $customer->id)
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        $balance = CustomerLedger::where('customer_id', $customer->id)->sum('amount');

        // Batch-resolve order numbers for the Reference column
        $reductionIds = [];
        $paymentIds   = [];
        $refundIds    = [];
        $directIds    = [];

        foreach ($entries as $entry) {
            if (!$entry->reference_id) continue;
            match ($entry->reference_type) {
                OrderReduction::class => $reductionIds[] = $entry->reference_id,
                Payment::class        => $paymentIds[]   = $entry->reference_id,
                Refund::class         => $refundIds[]    = $entry->reference_id,
                Order::class          => $directIds[]    = $entry->reference_id,
                default               => null,
            };
        }

        $pack     = fn($o) => $o ? ['id' => $o->id, 'number' => $o->order_number, 'catalogue' => $o->catalogue->name ?? '—'] : null;
        $orderMap    = [];
        $reductionMap = [];

        if ($reductionIds) {
            OrderReduction::whereIn('id', $reductionIds)
                ->with(['order.catalogue', 'items.design', 'reducedBy', 'refund.refundedBy'])
                ->get()
                ->each(function ($r) use (&$orderMap, &$reductionMap, $pack) {
                    $orderMap[OrderReduction::class . ':' . $r->id] = $pack($r->order);

                    $refund = null;
                    if ($r->refund) {
                        $rf = $r->refund;
                        $ext = $rf->refund_document ? strtolower(pathinfo($rf->refund_document, PATHINFO_EXTENSION)) : null;
                        $refund = [
                            'refund_date'      => $rf->refund_date->format('d M Y'),
                            'refund_method'    => $rf->refund_method === 'bank_transfer' ? 'Bank Transfer' : 'Cash',
                            'amount'           => 'PKR ' . number_format((float) $rf->amount, 0),
                            'refund_reference' => $rf->refund_reference,
                            'refund_document'  => $rf->refund_document ? \Storage::url($rf->refund_document) : null,
                            'doc_is_image'     => $ext && in_array($ext, ['jpg', 'jpeg', 'png']),
                        ];
                    }

                    $rows = [];
                    foreach ($r->items as $i) {
                        $did = $i->design_id;
                        if (!isset($rows[$did])) {
                            $rows[$did] = [
                                'name'       => $i->design->name ?? '—',
                                'unit_price' => (float) $i->unit_price,
                                'sizes'      => ['xs' => 0, 's' => 0, 'm' => 0, 'l' => 0, 'xl' => 0],
                                'qty'        => 0,
                                'amount'     => 0.0,
                            ];
                        }
                        $rows[$did]['sizes'][$i->size] = $i->qty_reduced;
                        $rows[$did]['qty']    += $i->qty_reduced;
                        $rows[$did]['amount'] += (float) $i->amount_reduced;
                    }

                    $sizeTotals = ['xs' => 0, 's' => 0, 'm' => 0, 'l' => 0, 'xl' => 0];
                    $qtyTotal   = 0;
                    foreach ($rows as $row) {
                        foreach ($sizeTotals as $key => $val) {
                            $sizeTotals[$key] += $row['sizes'][$key];
                        }
                        $qtyTotal += $row['qty'];
                    }

                    $reductionMap[$r->id] = [
                        'date'              => $r->reduction_date->format('d M Y'),
                        'logged_by'         => $r->reducedBy->name ?? '—',
                        'adjustment_type'   => ucwords(str_replace('_', ' ', $r->adjustment_type)),
                        'catalogue'         => $r->order->catalogue->name ?? '—',
                        'notes'             => $r->notes,
                        'original_total'    => 'PKR ' . number_format((float) $r->original_total, 0),
                        'adjustment_amount' => 'PKR ' . number_format((float) $r->adjustment_amount, 0),
                        'new_total'         => 'PKR ' . number_format((float) $r->new_total, 0),
                        'surplus_action'    => $r->surplus_action,
                        'items'             => collect($rows)->values()->map(fn($row) => [
                            'design'     => $row['name'],
                            'xs'         => $row['sizes']['xs'],
                            's'          => $row['sizes']['s'],
                            'm'          => $row['sizes']['m'],
                            'l'          => $row['sizes']['l'],
                            'xl'         => $row['sizes']['xl'],
                            'qty'        => $row['qty'],
                            'unit_price' => 'PKR ' . number_format($row['unit_price'], 0),
                            'amount'     => 'PKR ' . number_format($row['amount'], 0),
                        ])->toArray(),
                        'item_totals'       => [
                            'xs'  => $sizeTotals['xs'],
                            's'   => $sizeTotals['s'],
                            'm'   => $sizeTotals['m'],
                            'l'   => $sizeTotals['l'],
                            'xl'  => $sizeTotals['xl'],
                            'qty' => $qtyTotal,
                        ],
                        'refund'            => $refund,
                    ];
                });
        }
        if ($paymentIds) {
            Payment::whereIn('id', $paymentIds)
                ->with(['order' => fn($q) => $q->select('id', 'order_number', 'catalogue_id')->with('catalogue:id,name')])
                ->get()
                ->each(function ($p) use (&$orderMap, $pack) {
                    $packed = $pack($p->order);
                    if ($packed) {
                        $packed['payment_seq'] = $p->sequence_number;
                    }
                    $orderMap[Payment::class . ':' . $p->id] = $packed;
                });
        }
        if ($refundIds) {
            Refund::whereIn('id', $refundIds)
                ->with(['order' => fn($q) => $q->select('id', 'order_number', 'catalogue_id')->with('catalogue:id,name')])
                ->get()
                ->each(function ($r) use (&$orderMap, $pack) {
                    $orderMap[Refund::class . ':' . $r->id] = $pack($r->order);
                });
        }
        if ($directIds) {
            Order::whereIn('id', $directIds)
                ->with('catalogue:id,name')
                ->get(['id', 'order_number', 'catalogue_id'])
                ->each(function ($o) use (&$orderMap, $pack) {
                    $orderMap[Order::class . ':' . $o->id] = $pack($o);
                });
        }

        return view('customers.ledger', compact('customer', 'entries', 'balance', 'orderMap', 'reductionMap'));
    }

    public function pdf(Customer $customer)
    {
        $entries = CustomerLedger::where('customer_id', $customer->id)
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        $balance = $entries->sum('amount');

        $reductionIds = [];
        $paymentIds   = [];
        $refundIds    = [];
        $directIds    = [];

        foreach ($entries as $entry) {
            if (!$entry->reference_id) continue;
            match ($entry->reference_type) {
                OrderReduction::class => $reductionIds[] = $entry->reference_id,
                Payment::class        => $paymentIds[]   = $entry->reference_id,
                Refund::class         => $refundIds[]    = $entry->reference_id,
                Order::class          => $directIds[]    = $entry->reference_id,
                default               => null,
            };
        }

        $pack     = fn($o) => $o ? ['id' => $o->id, 'number' => $o->order_number, 'catalogue' => $o->catalogue->name ?? '—'] : null;
        $orderMap = [];

        if ($reductionIds) {
            OrderReduction::whereIn('id', $reductionIds)
                ->with(['order.catalogue'])
                ->get()
                ->each(function ($r) use (&$orderMap, $pack) {
                    $orderMap[OrderReduction::class . ':' . $r->id] = $pack($r->order);
                });
        }
        if ($paymentIds) {
            Payment::whereIn('id', $paymentIds)
                ->with(['order' => fn($q) => $q->select('id', 'order_number', 'catalogue_id')->with('catalogue:id,name')])
                ->get()
                ->each(function ($p) use (&$orderMap, $pack) {
                    $packed = $pack($p->order);
                    if ($packed) {
                        $packed['payment_seq'] = $p->sequence_number;
                    }
                    $orderMap[Payment::class . ':' . $p->id] = $packed;
                });
        }
        if ($refundIds) {
            Refund::whereIn('id', $refundIds)
                ->with(['order' => fn($q) => $q->select('id', 'order_number', 'catalogue_id')->with('catalogue:id,name')])
                ->get()
                ->each(function ($r) use (&$orderMap, $pack) {
                    $orderMap[Refund::class . ':' . $r->id] = $pack($r->order);
                });
        }
        if ($directIds) {
            Order::whereIn('id', $directIds)
                ->with('catalogue:id,name')
                ->get(['id', 'order_number', 'catalogue_id'])
                ->each(function ($o) use (&$orderMap, $pack) {
                    $orderMap[Order::class . ':' . $o->id] = $pack($o);
                });
        }

        return view('customers.ledger-pdf', compact('customer', 'entries', 'balance', 'orderMap'));
    }
}
