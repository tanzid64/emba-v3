<?php

namespace App\Exports;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Shared layout + styling for a "batch report" workbook: a centred
 * program title, subtitle, batch/timestamp line, a summary line, then a
 * styled, zebra-striped data grid frozen below its header.
 *
 * Concrete exports supply the data ({@see baseQuery()}, {@see query()},
 * {@see headings()}, {@see map()}) and the labels ({@see reportName()},
 * {@see summaryLine()}); everything visual is handled here so every
 * report sheet looks identical.
 *
 *   Row 1   — Program title — Report name (merged, large)
 *   Row 2   — Faculty / University subtitle (merged)
 *   Row 4   — Batch label + generated timestamp (merged)
 *   Row 5   — Summary line (merged)
 *   Row 7   — Column headings (styled)
 *   Row 8+  — Data rows
 */
abstract class BatchReportExport implements FromQuery, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithTitle
{
    protected int $totalRows = 0;

    protected string $programTitle = 'Executive MBA Program';

    protected string $subtitle = 'Faculty of Business Studies, University of Dhaka';

    public function __construct(protected readonly Batch $batch)
    {
        $this->totalRows = $this->baseQuery()->count();
    }

    /**
     * Unfiltered-by-ordering query used both for the row count and as the
     * basis for {@see query()}.
     */
    abstract protected function baseQuery(): Builder;

    /**
     * Report name shown after the program title on row 1 (e.g. "Viva Shortlist").
     */
    abstract protected function reportName(): string;

    /**
     * Human summary shown on row 5 (e.g. totals / cutoff context).
     */
    abstract protected function summaryLine(): string;

    /**
     * Data-grid column letters to right-align (numeric columns).
     *
     * @return list<string>
     */
    protected function rightAlignedColumns(): array
    {
        return [];
    }

    /**
     * Data-grid column letters to centre (short codes — roll, status…).
     *
     * @return list<string>
     */
    protected function centeredColumns(): array
    {
        return [];
    }

    public function startCell(): string
    {
        // Leaves rows 1-6 free for the report header injected by AfterSheet.
        return 'A7';
    }

    /**
     * Right-most column letter, derived from the heading count.
     */
    protected function lastColumn(): string
    {
        return Coordinate::stringFromColumnIndex(max(1, count($this->headings())));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $this->lastColumn();
                $headerRange = "A7:{$lastCol}7";
                $dataLastRow = 7 + $this->totalRows;

                // Row 1 — program title + report name.
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', $this->programTitle.' — '.$this->reportName());
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 2 — subtitle.
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', $this->subtitle);
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 11, 'italic' => true, 'color' => ['rgb' => '4B5563']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 4 — batch + generated timestamp.
                $sheet->mergeCells("A4:{$lastCol}4");
                $sheet->setCellValue('A4', sprintf(
                    'Batch: %s (%s)     |     Generated: %s',
                    $this->batch->name,
                    $this->batch->code,
                    now()->format('d M Y, h:i A'),
                ));
                $sheet->getStyle('A4')->getFont()->setBold(true);

                // Row 5 — summary line.
                $sheet->mergeCells("A5:{$lastCol}5");
                $sheet->setCellValue('A5', $this->summaryLine());
                $sheet->getStyle('A5')->applyFromArray([
                    'font' => ['size' => 10, 'color' => ['rgb' => '374151']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);

                // Row 7 — column header styling.
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2F1B72'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(7)->setRowHeight(22);
                $sheet->getStyle($headerRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('1F2937');

                if ($this->totalRows > 0) {
                    $dataRange = "A8:{$lastCol}{$dataLastRow}";
                    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('D1D5DB');

                    foreach ($this->rightAlignedColumns() as $col) {
                        $sheet->getStyle("{$col}8:{$col}{$dataLastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    foreach ($this->centeredColumns() as $col) {
                        $sheet->getStyle("{$col}8:{$col}{$dataLastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    // Zebra striping — light gray fill on every other data row.
                    for ($row = 9; $row <= $dataLastRow; $row += 2) {
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F3F4F6');
                    }
                }

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->freezePane('A8');
            },
        ];
    }
}
