<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablesByBankExport
{
    public function __construct(
        private $orders,
        private $bankAccounts,
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

        $headers = ['#', 'Customer', 'City', 'Outstanding', 'Title Given'];
        foreach ($this->bankAccounts as $ba) {
            $headers[] = $ba->title;
        }
        $headers[] = 'Cash / Adv.';

        $colCount = count($headers);
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);

        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1D1D1F']],
        ]);

        $row = 2;
        foreach ($this->orders as $i => $order) {
            $rowData = [
                $i + 1,
                $order->customer?->name ?? $order->submitted_name,
                $order->customer?->city ?? $order->submitted_city,
                (float) $order->outstanding_balance,
                $order->title_given_label === '—' ? '' : $order->title_given_label,
            ];
            foreach ($this->bankAccounts as $ba) {
                $rowData[] = (float) ($order->bank_totals[$ba->id] ?? 0);
            }
            $rowData[] = (float) $order->misc_total;

            $sheet->fromArray($rowData, null, "A{$row}");
            $row++;
        }

        $totalRow = ['', '', 'Total', (float) $this->orders->sum('outstanding_balance'), ''];
        foreach ($this->bankAccounts as $ba) {
            $totalRow[] = (float) $this->orders->sum(fn($o) => $o->bank_totals[$ba->id] ?? 0);
        }
        $totalRow[] = (float) $this->orders->sum('misc_total');

        $sheet->fromArray($totalRow, null, "A{$row}");

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
