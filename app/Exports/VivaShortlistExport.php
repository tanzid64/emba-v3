<?php

namespace App\Exports;

use App\Models\AdmissionResult;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Excel export of the viva shortlist — every AdmissionResult whose MCQ
 * mark reaches the batch's eligibility cutoff, highest MCQ first.
 */
class VivaShortlistExport extends BatchReportExport
{
    private readonly float $threshold;

    public function __construct(Batch $batch)
    {
        $this->threshold = (float) ($batch->admissionSetting?->viva_mcq_threshold
            ?? config('result.viva_mcq_threshold'));

        parent::__construct($batch);
    }

    public function query()
    {
        return $this->baseQuery()
            ->with(['applicant.profile:id,applicant_id,full_name,father_name'])
            ->orderByDesc('mcq_marks')
            ->orderByDesc('total_marks');
    }

    public function headings(): array
    {
        return [
            'Roll',
            'Application ID',
            'Name',
            "Father's Name",
            'MCQ',
            'Written',
            'Total',
        ];
    }

    public function map($result): array
    {
        $profile = $result->applicant?->profile;

        return [
            $result->roll_number,
            $result->application_number,
            $profile?->full_name,
            $profile?->father_name,
            (float) $result->mcq_marks,
            (float) $result->written_marks,
            (float) $result->total_marks,
        ];
    }

    public function title(): string
    {
        return 'Viva Shortlist';
    }

    protected function reportName(): string
    {
        return 'Viva Shortlist';
    }

    protected function summaryLine(): string
    {
        $cutoff = rtrim(rtrim(number_format($this->threshold, 2), '0'), '.');

        return "Eligible for viva: {$this->totalRows}     |     MCQ cutoff: {$cutoff} / ".config('result.max_mcq_marks');
    }

    protected function rightAlignedColumns(): array
    {
        return ['E', 'F', 'G'];
    }

    protected function centeredColumns(): array
    {
        return ['A'];
    }

    protected function baseQuery(): Builder
    {
        return AdmissionResult::query()
            ->where('batch_id', $this->batch->id)
            ->where('mcq_marks', '>=', $this->threshold);
    }
}
