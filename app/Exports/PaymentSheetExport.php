<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentSheetExport
{
    public function __construct(
        private $orders,
        private $catalogue,
        private $bankAccounts,
        private bool $hideFinancials = false
    ) {}

    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = $this->build();
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($this->catalogue->name, 0, 31));

        $bankHeaders = $this->bankAccounts->pluck('title')->toArray();

        if ($this->hideFinancials) {
            $headers = ['#', 'Customer', 'City', 'XS', 'S', 'M', 'L', 'XL', 'Qty/Dsn', 'Total Qty', 'Status', 'Payment'];
        } else {
            $headers = array_merge(
                ['#', 'Customer', 'City', 'XS', 'S', 'M', 'L', 'XL', 'Qty/Dsn', 'Total Qty', 'Rate', 'Total Bill', 'Received', 'Receivable', 'Title Given'],
                $bankHeaders,
                ['Misc']
            );
        }

        $colCount = count($headers);
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1D1D1F']],
        ]);

        $totals = [
            'xs' => 0, 's' => 0, 'm' => 0, 'l' => 0, 'xl' => 0,
            'qty_per_design' => 0, 'total_qty' => 0,
            'total_bill' => 0, 'received' => 0, 'receivable' => 0, 'misc' => 0,
        ];
        if (!$this->hideFinancials) {
            foreach ($this->bankAccounts as $bank) {
                $totals['bank_' . $bank->id] = 0;
            }
        }

        $row = 2;
        foreach ($this->orders as $i => $order) {
            $item         = $order->items->first();
            $xs           = (int) ($item?->qty_xs ?? 0);
            $s            = (int) ($item?->qty_s  ?? 0);
            $m            = (int) ($item?->qty_m  ?? 0);
            $l            = (int) ($item?->qty_l  ?? 0);
            $xl           = (int) ($item?->qty_xl ?? 0);
            $qtyPerDesign = $xs + $s + $m + $l + $xl;
            $totalQty     = $qtyPerDesign * $this->catalogue->number_of_designs;
            $rate         = $totalQty > 0 ? (int) round($order->total_amount / $totalQty) : 0;

            $totals['xs']             += $xs;
            $totals['s']              += $s;
            $totals['m']              += $m;
            $totals['l']              += $l;
            $totals['xl']             += $xl;
            $totals['qty_per_design'] += $qtyPerDesign;
            $totals['total_qty']      += $totalQty;

            if ($this->hideFinancials) {
                $paymentStatus = $order->total_paid <= 0
                    ? 'Not Paid'
                    : ($order->outstanding_balance <= 0 ? 'Fully Paid' : 'Partially Paid');

                $rowData = [
                    $i + 1,
                    $order->customer?->name ?? $order->submitted_name,
                    $order->submitted_city,
                    $xs ?: '',
                    $s  ?: '',
                    $m  ?: '',
                    $l  ?: '',
                    $xl ?: '',
                    $qtyPerDesign ?: '',
                    $totalQty     ?: '',
                    ucfirst(str_replace('_', ' ', $order->status)),
                    $paymentStatus,
                ];
            } else {
                $bankPmts   = [];
                $miscAmt    = 0;
                foreach ($order->payments as $payment) {
                    if ($payment->payment_type === 'advance') {
                        $miscAmt += $payment->amount;
                    } elseif ($payment->payment_type === 'bank_transfer' && $payment->bank_account_id) {
                        $bankPmts[$payment->bank_account_id] = ($bankPmts[$payment->bank_account_id] ?? 0) + $payment->amount;
                    }
                }
                $titleGiven = $order->payments
                    ->where('payment_type', 'bank_transfer')
                    ->filter(fn($p) => $p->bankAccount)
                    ->map(fn($p) => $p->bankAccount->title)
                    ->unique()
                    ->implode('/');

                $amountReceived  = min((float) $order->total_paid, (float) $order->total_amount);
                $totalBankPaid   = array_sum($bankPmts);
                $cappedBankTotal = max(0.0, $amountReceived - $miscAmt);
                if ($totalBankPaid > 0) {
                    $scale = $cappedBankTotal / $totalBankPaid;
                    foreach ($bankPmts as $bankId => $amt) {
                        $bankPmts[$bankId] = round($amt * $scale);
                    }
                }

                $totals['total_bill'] += $order->total_amount;
                $totals['received']   += $amountReceived;
                $totals['receivable'] += $order->outstanding_balance;
                $totals['misc']       += $miscAmt;
                foreach ($this->bankAccounts as $bank) {
                    $totals['bank_' . $bank->id] += ($bankPmts[$bank->id] ?? 0);
                }

                $bankValues = [];
                foreach ($this->bankAccounts as $bank) {
                    $bankValues[] = ($bankPmts[$bank->id] ?? 0) ?: '';
                }

                $rowData = array_merge(
                    [
                        $i + 1,
                        $order->customer?->name ?? $order->submitted_name,
                        $order->submitted_city,
                        $xs ?: '',
                        $s  ?: '',
                        $m  ?: '',
                        $l  ?: '',
                        $xl ?: '',
                        $qtyPerDesign ?: '',
                        $totalQty     ?: '',
                        $rate > 0 ? $rate : '',
                        $order->total_amount,
                        $amountReceived > 0 ? $amountReceived : '',
                        $order->outstanding_balance > 0 ? $order->outstanding_balance : '',
                        $titleGiven,
                    ],
                    $bankValues,
                    [$miscAmt > 0 ? $miscAmt : '']
                );
            }

            $sheet->fromArray($rowData, null, "A{$row}");

            if ($i % 2 === 1) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9F9F9']],
                ]);
            }

            $row++;
        }

        // Totals row
        $label = 'TOTAL (' . $this->orders->count() . ' orders)';

        if ($this->hideFinancials) {
            $totalsRow = [
                '', '',
                $label,
                $totals['xs'] > 0 ? $totals['xs'] : '',
                $totals['s']  > 0 ? $totals['s']  : '',
                $totals['m']  > 0 ? $totals['m']  : '',
                $totals['l']  > 0 ? $totals['l']  : '',
                $totals['xl'] > 0 ? $totals['xl'] : '',
                $totals['qty_per_design'] > 0 ? $totals['qty_per_design'] : '',
                $totals['total_qty']      > 0 ? $totals['total_qty']      : '',
                '', '',
            ];
        } else {
            $bankTotalValues = [];
            foreach ($this->bankAccounts as $bank) {
                $bankTotalValues[] = $totals['bank_' . $bank->id] > 0 ? $totals['bank_' . $bank->id] : '';
            }

            $totalsRow = array_merge(
                [
                    '', '',
                    $label,
                    $totals['xs'] > 0 ? $totals['xs'] : '',
                    $totals['s']  > 0 ? $totals['s']  : '',
                    $totals['m']  > 0 ? $totals['m']  : '',
                    $totals['l']  > 0 ? $totals['l']  : '',
                    $totals['xl'] > 0 ? $totals['xl'] : '',
                    $totals['qty_per_design'] > 0 ? $totals['qty_per_design'] : '',
                    $totals['total_qty']      > 0 ? $totals['total_qty']      : '',
                    '',
                    $totals['total_bill'],
                    $totals['received'],
                    $totals['receivable'],
                    '',
                ],
                $bankTotalValues,
                [$totals['misc'] > 0 ? $totals['misc'] : '']
            );
        }

        $sheet->fromArray($totalsRow, null, "A{$row}");
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8E8ED']],
        ]);

        foreach (range(1, $colCount) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
