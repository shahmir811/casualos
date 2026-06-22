<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankCollectionExport
{
    public function __construct(
        private $banks,
        private array $rows,
        private array $collected,
        private array $expected,
        private array $receivable,
        private float $miscAmount,
        private float $grandCollected,
        private float $grandExpected,
        private float $grandReceivable,
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

    /** Convert 1-based column + row integers to an A1-style reference. */
    private function ref(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col) . $row;
    }

    /** Lacs-format a positive number; blank for zero/empty. */
    private function fmt(float|int $value): string
    {
        return $value > 0 ? number_format($value) : '';
    }

    private function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($this->selectedCatalogue->name, 0, 31));

        $bankCount     = $this->banks->count();
        $lastDataCol   = 15 + $bankCount + 4; // A–O fixed + banks + misc + 3 summary cols
        $lastColLetter = Coordinate::stringFromColumnIndex($lastDataCol);

        // ── Row 1: report title ───────────────────────────────────────────────
        $sheet->setCellValue('A1', 'Bank Collection Report — ' . $this->selectedCatalogue->name);
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF1D1D1F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Row 2: sub-title ─────────────────────────────────────────────────
        $sheet->setCellValue('A2', 'Casualite — Collected vs expected per bank account | Generated: ' . now()->format('d M Y, h:i A'));
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 9, 'color' => ['argb' => 'FF6E6E73']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Row 4: column headers ─────────────────────────────────────────────
        $headerRow = 4;
        $headers   = [
            'Sr#', 'Customer Name', 'City',
            "Extra\nSmall", 'Small', 'Medium', 'Large', "Extra\nLarge",
            "Total\nQty", "Over All\nTotal Qty", 'Rate',
            'Total Bill', "Amount\nReceived", "Amount\nReceivable",
            'Title Given',
        ];
        foreach ($this->banks as $bank) {
            $headers[] = $bank->title;
        }
        $headers[] = "Misc /\nPrev. Balance";
        $headers[] = "Amount\nReceived";
        $headers[] = "Amount\nReceivable";
        $headers[] = 'Total Bill';

        foreach ($headers as $i => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $header);
            $sheet->getStyle("{$colLetter}{$headerRow}")->getAlignment()->setWrapText(true);
        }

        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1D1D1F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF444444']]],
        ]);
        $sheet->getStyle("L{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFA500');
        $sheet->getStyle("M{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF22C55E');
        $sheet->getStyle("N{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFA500');
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // ── Data rows ─────────────────────────────────────────────────────────
        $dataStartRow = $headerRow + 1;
        $bankTotals   = array_fill_keys($this->banks->pluck('id')->all(), 0.0);
        $totXs = $totS = $totM = $totL = $totXl = $totTotalQty = $totOverAllQty = 0;
        $totMisc = 0.0;

        foreach ($this->rows as $i => $row) {
            $r   = $dataStartRow + $i;
            $col = 1;

            $sheet->setCellValue($this->ref($col++, $r), $i + 1);
            $sheet->setCellValue($this->ref($col++, $r), $row['name']);
            $sheet->setCellValue($this->ref($col++, $r), $row['city']);
            $sheet->setCellValue($this->ref($col++, $r), $row['qty_xs'] ?: '');
            $sheet->setCellValue($this->ref($col++, $r), $row['qty_s']  ?: '');
            $sheet->setCellValue($this->ref($col++, $r), $row['qty_m']  ?: '');
            $sheet->setCellValue($this->ref($col++, $r), $row['qty_l']  ?: '');
            $sheet->setCellValue($this->ref($col++, $r), $row['qty_xl'] ?: '');
            $sheet->setCellValue($this->ref($col++, $r), $row['total_qty']   ?: '');
            $sheet->setCellValue($this->ref($col++, $r), $row['over_all_qty'] ?: '');
            $sheet->setCellValue($this->ref($col++, $r), $row['rate'] ? number_format($row['rate']) : '');
            $sheet->setCellValue($this->ref($col++, $r), number_format($row['total_bill']));
            $sheet->setCellValue($this->ref($col++, $r), $this->fmt($row['amount_received']));
            $sheet->setCellValue($this->ref($col++, $r), $this->fmt($row['amount_receivable']));
            $sheet->setCellValue($this->ref($col++, $r), $row['title_given']);

            foreach ($this->banks as $bank) {
                $amt = $row['bank_payments'][$bank->id] ?? 0.0;
                $sheet->setCellValue($this->ref($col++, $r), $this->fmt($amt));
                $bankTotals[$bank->id] += $amt;
            }

            $sheet->setCellValue($this->ref($col++, $r), $this->fmt($row['misc']));
            $sheet->setCellValue($this->ref($col++, $r), $this->fmt($row['amount_received']));
            $sheet->setCellValue($this->ref($col++, $r), $this->fmt($row['amount_receivable']));
            $sheet->setCellValue($this->ref($col++, $r), number_format($row['total_bill']));

            $totXs         += $row['qty_xs'];
            $totS          += $row['qty_s'];
            $totM          += $row['qty_m'];
            $totL          += $row['qty_l'];
            $totXl         += $row['qty_xl'];
            $totTotalQty   += $row['total_qty'];
            $totOverAllQty += $row['over_all_qty'];
            $totMisc       += $row['misc'];

            $bgColor = ($i % 2 === 0) ? 'FFFFFFFF' : 'FFF9F9F9';
            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD2D2D7']]],
                'font'    => ['size' => 9],
            ]);
            $sheet->getStyle("L{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF9C4');
            $sheet->getStyle("M{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD1FAE5');
            $sheet->getStyle("N{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF9C4');

            $amtStartLetter = Coordinate::stringFromColumnIndex(12);
            $sheet->getStyle("D{$r}:K{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$amtStartLetter}{$r}:{$lastColLetter}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // ── Total row ─────────────────────────────────────────────────────────
        $totalRow = $dataStartRow + count($this->rows);
        $this->writeSummaryRow($sheet, $totalRow, [
            $this->ref(1, $totalRow)  => (string) count($this->rows),
            $this->ref(2, $totalRow)  => 'Total',
            $this->ref(4, $totalRow)  => (string) $totXs,
            $this->ref(5, $totalRow)  => (string) $totS,
            $this->ref(6, $totalRow)  => (string) $totM,
            $this->ref(7, $totalRow)  => (string) $totL,
            $this->ref(8, $totalRow)  => (string) $totXl,
            $this->ref(9, $totalRow)  => (string) $totTotalQty,
            $this->ref(10, $totalRow) => (string) $totOverAllQty,
            $this->ref(12, $totalRow) => number_format($this->grandExpected),
            $this->ref(13, $totalRow) => number_format($this->grandCollected),
            $this->ref(14, $totalRow) => number_format($this->grandReceivable),
        ], $bankTotals, $totalRow, $this->fmt($totMisc), number_format($this->grandCollected), number_format($this->grandReceivable), number_format($this->grandExpected));

        $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E5EA']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF999999']],
                          'top'        => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1D1D1F']]],
        ]);
        $amtStartLetter = Coordinate::stringFromColumnIndex(12);
        $sheet->getStyle("D{$totalRow}:K{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$amtStartLetter}{$totalRow}:{$lastColLetter}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // ── Total Payment row (per-bank expected) ─────────────────────────────
        $tpRow = $totalRow + 1;
        $col   = 2;
        $sheet->setCellValue($this->ref($col++, $tpRow), 'Total Payment');
        $col = 12;
        $sheet->setCellValue($this->ref($col++, $tpRow), number_format($this->grandExpected));
        $col = 16;
        foreach ($this->banks as $bank) {
            $sheet->setCellValue($this->ref($col++, $tpRow), $this->fmt($this->expected[$bank->id] ?? 0));
        }

        $sheet->getStyle("A{$tpRow}:{$lastColLetter}{$tpRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF1D4ED8']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFDBFE']]],
        ]);
        $amtStartLetter = Coordinate::stringFromColumnIndex(12);
        $sheet->getStyle("{$amtStartLetter}{$tpRow}:{$lastColLetter}{$tpRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // ── Receivable row (per-bank outstanding) ─────────────────────────────
        $rcvRow = $totalRow + 2;
        $col    = 2;
        $sheet->setCellValue($this->ref($col++, $rcvRow), 'Receivable');
        $col = 14;
        $sheet->setCellValue($this->ref($col++, $rcvRow), number_format($this->grandReceivable));
        $col = 16;
        foreach ($this->banks as $bank) {
            $sheet->setCellValue($this->ref($col++, $rcvRow), $this->fmt($this->receivable[$bank->id] ?? 0));
        }
        // right-side summary: skip misc, skip received, write receivable, skip total bill
        $miscColNum = 16 + $bankCount;
        $sheet->setCellValue($this->ref($miscColNum + 2, $rcvRow), number_format($this->grandReceivable));

        $sheet->getStyle("A{$rcvRow}:{$lastColLetter}{$rcvRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF92400E']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF9C3']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFDE68A']]],
        ]);
        $amtStartLetter = Coordinate::stringFromColumnIndex(12);
        $sheet->getStyle("{$amtStartLetter}{$rcvRow}:{$lastColLetter}{$rcvRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // ── Footnote ──────────────────────────────────────────────────────────
        $footnoteRow = $rcvRow + 2;
        $sheet->setCellValue("A{$footnoteRow}", 'Misc / Prev. Balance = advance credits applied + cash payments not attributed to a specific bank account.');
        $sheet->mergeCells("A{$footnoteRow}:{$lastColLetter}{$footnoteRow}");
        $sheet->getStyle("A{$footnoteRow}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 8, 'color' => ['argb' => 'FF86868B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // ── Column widths ─────────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(12);
        foreach (['D','E','F','G','H'] as $c) {
            $sheet->getColumnDimension($c)->setWidth(7);
        }
        $sheet->getColumnDimension('I')->setWidth(8);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(10);
        $sheet->getColumnDimension('L')->setWidth(13);
        $sheet->getColumnDimension('M')->setWidth(13);
        $sheet->getColumnDimension('N')->setWidth(13);
        $sheet->getColumnDimension('O')->setWidth(14);

        $bankColStart = 16;
        foreach ($this->banks as $idx => $bank) {
            $colLetter = Coordinate::stringFromColumnIndex($bankColStart + $idx);
            $sheet->getColumnDimension($colLetter)->setWidth(13);
        }

        $miscColLetter     = Coordinate::stringFromColumnIndex($bankColStart + $bankCount);
        $summaryCol1Letter = Coordinate::stringFromColumnIndex($bankColStart + $bankCount + 1);
        $summaryCol2Letter = Coordinate::stringFromColumnIndex($bankColStart + $bankCount + 2);
        $summaryCol3Letter = Coordinate::stringFromColumnIndex($bankColStart + $bankCount + 3);
        $sheet->getColumnDimension($miscColLetter)->setWidth(14);
        $sheet->getColumnDimension($summaryCol1Letter)->setWidth(13);
        $sheet->getColumnDimension($summaryCol2Letter)->setWidth(13);
        $sheet->getColumnDimension($summaryCol3Letter)->setWidth(13);

        $sheet->freezePane('P' . ($headerRow + 1));

        return $spreadsheet;
    }

    /** Write per-bank and right-side summary values into a summary row. */
    private function writeSummaryRow(
        Worksheet $sheet,
        int $row,
        array $fixedCells,
        array $bankTotals,
        int $rowNum,
        string $misc,
        string $summaryReceived,
        string $summaryReceivable,
        string $summaryBill,
    ): void {
        foreach ($fixedCells as $ref => $value) {
            $sheet->setCellValue($ref, $value);
        }

        $bankColStart = 16;
        foreach ($this->banks as $idx => $bank) {
            $colNum = $bankColStart + $idx;
            $sheet->setCellValue($this->ref($colNum, $row), $bankTotals[$bank->id] > 0 ? number_format($bankTotals[$bank->id]) : '');
        }

        $miscColNum = 16 + $this->banks->count();
        $sheet->setCellValue($this->ref($miscColNum,     $row), $misc);
        $sheet->setCellValue($this->ref($miscColNum + 1, $row), $summaryReceived);
        $sheet->setCellValue($this->ref($miscColNum + 2, $row), $summaryReceivable);
        $sheet->setCellValue($this->ref($miscColNum + 3, $row), $summaryBill);
    }
}
