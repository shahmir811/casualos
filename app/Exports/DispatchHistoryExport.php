<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DispatchHistoryExport
{
    public function __construct(
        private $orders,
        private $selectedCatalogue
    ) {}

    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = $this->build();
        $writer      = new Xlsx($spreadsheet);

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
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($this->selectedCatalogue->name, 0, 31));

        $statusLabels = [
            'confirmed'            => 'Confirmed',
            'stitching'            => 'Stitching',
            'partially_dispatched' => 'Partially Dispatched',
            'dispatched'           => 'Dispatched',
        ];

        $headers  = ['#', 'Customer', 'City', 'Order #', 'Status', 'Total Ordered', 'Dispatched', 'Remaining', 'First Dispatch Date'];
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
                $order->customer?->city ?? $order->submitted_city ?? '',
                '#' . $order->order_number,
                $statusLabels[$order->status] ?? $order->status,
                $order->total_ordered,
                $order->total_dispatched,
                $order->total_remaining,
                $order->first_dispatch?->format('d M Y') ?? '—',
            ], null, "A{$row}");
            $row++;
        }

        // Totals row
        $sheet->fromArray([
            '', '', '', 'Total', '',
            $this->orders->sum('total_ordered'),
            $this->orders->sum('total_dispatched'),
            $this->orders->sum('total_remaining'),
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
