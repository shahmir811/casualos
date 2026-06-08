<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablesByBankExport
{
    public function __construct(
        private $banks,
        private array $rows,
        private float $grandReceivable,
        private array $bankReceivables,
        private float $miscReceivable,
        private $selectedCatalogue,
    ) {}

    public function download(string $filename): StreamedResponse
    {
        $writer = new Xlsx($this->build());

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

        $bankCount     = $this->banks->count();
        $lastDataCol   = 5 + $bankCount + 1; // #, Name, City, Receivable, Title + banks + Misc
        $lastColLetter = Coordinate::stringFromColumnIndex($lastDataCol);

        // ── Row 1: title ──────────────────────────────────────────────────────
        $sheet->setCellValue('A1', 'Receivables by Bank — ' . $this->selectedCatalogue->name);
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF1D1D1F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Row 2: subtitle ───────────────────────────────────────────────────
        $sheet->setCellValue('A2', 'Casualite — Outstanding balances grouped by assigned bank | Generated: ' . now()->format('d M Y, h:i A'));
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 9, 'color' => ['argb' => 'FF6E6E73']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Row 4: headers ────────────────────────────────────────────────────
        $headerRow = 4;
        $headers   = ['#', 'Customer Name', 'City', 'Receivable', 'Title Given'];
        foreach ($this->banks as $bank) {
            $headers[] = $bank->title;
        }
        $headers[] = 'Misc';

        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $headerRow, $header);
        }

        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1D1D1F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF444444']]],
        ]);
        // Receivable header in orange
        $sheet->getStyle('D' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFA500');
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        // ── Data rows ─────────────────────────────────────────────────────────
        $dataStartRow = $headerRow + 1;

        foreach ($this->rows as $i => $row) {
            $r   = $dataStartRow + $i;
            $col = 1;

            $sheet->setCellValue($this->ref($col++, $r), $i + 1);
            $sheet->setCellValue($this->ref($col++, $r), $row['name']);
            $sheet->setCellValue($this->ref($col++, $r), $row['city']);
            $sheet->setCellValue($this->ref($col++, $r), $row['receivable'] > 0 ? lacs_format($row['receivable']) : '');
            $sheet->setCellValue($this->ref($col++, $r), $row['title_given']);

            foreach ($this->banks as $bank) {
                $amt = $row['bank_rcv'][$bank->id] ?? 0.0;
                $sheet->setCellValue($this->ref($col++, $r), $amt > 0 ? lacs_format($amt) : '');
            }

            $sheet->setCellValue($this->ref($col++, $r), $row['misc'] > 0 ? lacs_format($row['misc']) : '');

            $bgColor = ($i % 2 === 0) ? 'FFFFFFFF' : 'FFF9F9F9';
            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD2D2D7']]],
                'font'    => ['size' => 9],
            ]);
            $sheet->getStyle("D{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF9C4');
            $sheet->getStyle("D{$r}:{$lastColLetter}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // ── Total row ─────────────────────────────────────────────────────────
        $totalRow = $dataStartRow + count($this->rows);
        $col      = 1;
        $sheet->setCellValue($this->ref($col++, $totalRow), count($this->rows));
        $sheet->setCellValue($this->ref($col++, $totalRow), 'Total');
        $col++; // city blank
        $sheet->setCellValue($this->ref($col++, $totalRow), lacs_format($this->grandReceivable));
        $col++; // title blank

        foreach ($this->banks as $bank) {
            $amt = $this->bankReceivables[$bank->id] ?? 0.0;
            $sheet->setCellValue($this->ref($col++, $totalRow), $amt > 0 ? lacs_format($amt) : '');
        }
        $sheet->setCellValue($this->ref($col++, $totalRow), $this->miscReceivable > 0 ? lacs_format($this->miscReceivable) : '');

        $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E5EA']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF999999']],
                          'top'        => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1D1D1F']]],
        ]);
        $sheet->getStyle("D{$totalRow}:{$lastColLetter}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // ── Column widths ─────────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(14);

        $bankColStart = 6;
        foreach ($this->banks as $idx => $bank) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($bankColStart + $idx))->setWidth(14);
        }
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($bankColStart + $bankCount))->setWidth(14);

        return $spreadsheet;
    }

    private function ref(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col) . $row;
    }
}
