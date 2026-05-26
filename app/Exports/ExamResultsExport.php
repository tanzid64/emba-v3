<?php

namespace App\Exports;

use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\Batch;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel export of every AdmissionResult row for a batch, filtered by
 * status and merit-position range as chosen on the admin page.
 *
 * Layout:
 *   Row 1   — Program title (merged, large)
 *   Row 2   — Faculty / University subtitle (merged)
 *   Row 3   — Blank spacer
 *   Row 4   — Batch label (left) + Generated timestamp (right)
 *   Row 5   — Summary line (totals + filter context)
 *   Row 6   — Blank spacer
 *   Row 7   — Column headings (styled)
 *   Row 8+  — Data rows
 */
class ExamResultsExport implements FromQuery, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithTitle
{
    private int $totalRows = 0;

    private int $passedRows = 0;

    private int $failedRows = 0;

    public function __construct(
        private readonly Batch $batch,
        private readonly ?string $statusFilter = null,
        private readonly ?int $meritFrom = null,
        private readonly ?int $meritTo = null,
    ) {
        $this->computeSummary();
    }

    public function query()
    {
        return $this->baseQuery()
            ->with(['applicant.profile:id,applicant_id,full_name,mother_name,father_name'])
            ->orderByRaw('merit_position IS NULL')
            ->orderBy('merit_position')
            ->orderByDesc('total_marks');
    }

    public function headings(): array
    {
        return [
            'Merit',
            'Roll',
            'Application ID',
            'Name',
            'Father\'s Name',
            'MCQ',
            'Written',
            'Viva',
            'Schooling',
            'Experience',
            'Total',
            'Status',
        ];
    }

    public function map($result): array
    {
        $profile = $result->applicant?->profile;

        return [
            $result->merit_position,
            $result->roll_number,
            $result->application_number,
            $profile?->full_name,
            $profile?->father_name,
            (float) $result->mcq_marks,
            (float) $result->written_marks,
            (float) $result->viva_marks,
            (float) $result->schooling_marks,
            (float) $result->experience_marks,
            (float) $result->total_marks,
            $result->status?->label() ?? '',
        ];
    }

    public function startCell(): string
    {
        // Leaves rows 1-6 free for the report header injected by AfterSheet.
        return 'A7';
    }

    public function title(): string
    {
        return 'Exam Results';
    }

    /**
     * Inject the report header rows and apply professional styling.
     * AfterSheet runs after FromQuery + headings + map have already
     * written rows 7+, so we know exactly where data ends.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'L';
                $headerRange = "A7:{$lastCol}7";
                $dataLastRow = 7 + $this->totalRows;

                // Row 1 — program title.
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', 'Executive MBA Program — Exam Results');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 2 — subtitle.
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'Faculty of Business Studies, University of Dhaka');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 11, 'italic' => true, 'color' => ['rgb' => '4B5563']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 4 — batch (left) + generated timestamp (right).
                $sheet->setCellValue('A4', "Batch: {$this->batch->name} ({$this->batch->code})");
                $sheet->getStyle('A4')->getFont()->setBold(true);
                $sheet->mergeCells('A4:F4');

                $sheet->setCellValue('G4', 'Generated: '.now()->format('d M Y, h:i A'));
                $sheet->getStyle('G4')->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'font' => ['color' => ['rgb' => '4B5563']],
                ]);
                $sheet->mergeCells("G4:{$lastCol}4");

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

                // Data range — borders + numeric right-alignment.
                if ($this->totalRows > 0) {
                    $dataRange = "A8:{$lastCol}{$dataLastRow}";
                    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('D1D5DB');

                    // Right-align the numeric mark columns (F-K).
                    $sheet->getStyle("F8:K{$dataLastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Center merit + roll + status.
                    $sheet->getStyle("A8:C{$dataLastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("L8:L{$dataLastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Zebra striping — light gray fill on every other data row.
                    for ($row = 8; $row <= $dataLastRow; $row++) {
                        $isEvenDataRow = ($row - 8) % 2 === 1;
                        if (! $isEvenDataRow) {
                            continue;
                        }

                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F3F4F6');
                    }
                }

                // Auto-size all data columns.
                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getStyle($headerRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('1F2937');

                $sheet->freezePane('A8');
            },
        ];
    }

    private function baseQuery()
    {
        return AdmissionResult::query()
            ->where('batch_id', $this->batch->id)
            ->when(
                $this->statusFilter !== null && $this->statusFilter !== '',
                fn ($q) => $q->where('status', $this->statusFilter),
            )
            ->when(
                $this->meritFrom !== null,
                fn ($q) => $q->where('merit_position', '>=', $this->meritFrom),
            )
            ->when(
                $this->meritTo !== null,
                fn ($q) => $q->where('merit_position', '<=', $this->meritTo),
            );
    }

    private function computeSummary(): void
    {
        $this->totalRows = (clone $this->baseQuery())->count();
        $this->passedRows = (clone $this->baseQuery())
            ->where('status', ResultStatusEnum::PASSED->value)->count();
        $this->failedRows = $this->totalRows - $this->passedRows;
    }

    private function summaryLine(): string
    {
        $parts = [
            "Total: {$this->totalRows}",
            "Passed: {$this->passedRows}",
            "Failed: {$this->failedRows}",
        ];

        if ($this->statusFilter !== null && $this->statusFilter !== '') {
            $parts[] = "Status filter: {$this->statusFilter}";
        }

        if ($this->meritFrom !== null || $this->meritTo !== null) {
            $from = $this->meritFrom ?? '∞';
            $to = $this->meritTo ?? '∞';
            $parts[] = "Merit range: {$from} – {$to}";
        }

        return implode('   |   ', $parts);
    }
}
