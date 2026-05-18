<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerOrderBillExport
{
    public function __construct(
        private $orders,
        private $selectedCatalogue
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
        $sheet->setTitle(substr($this->selectedCatalogue->name, 0, 31));

        $headers = ['#', 'Customer', 'City', 'XS', 'S', 'M', 'L', 'XL', 'Total Qty', 'Rate', 'Total Bill', 'Received', 'Receivable', 'Title Given'];
        $colCount = count($headers);
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);

        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1D1D1F']],
        ]);

        $row = 2;
        foreach ($this->orders as $i => $order) {
            $sheet->fromArray([
                $i + 1,
                $order->customer?->name ?? $order->submitted_name,
                $order->customer?->city ?? $order->submitted_city,
                $order->agg_xs,
                $order->agg_s,
                $order->agg_m,
                $order->agg_l,
                $order->agg_xl,
                $order->agg_total,
                $order->agg_rate,
                $order->total_amount,
                $order->total_paid,
                $order->outstanding_balance,
                $order->title_given_label === '—' ? '' : $order->title_given_label,
            ], null, "A{$row}");
            $row++;
        }

        $sheet->fromArray([
            '', '', 'Total',
            $this->orders->sum('agg_xs'),
            $this->orders->sum('agg_s'),
            $this->orders->sum('agg_m'),
            $this->orders->sum('agg_l'),
            $this->orders->sum('agg_xl'),
            $this->orders->sum('agg_total'),
            '',
            $this->orders->sum('total_amount'),
            $this->orders->sum('total_paid'),
            $this->orders->sum('outstanding_balance'),
            '',
        ], null, "A{$row}");

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF5F5F7']],
        ]);

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
