<?php

namespace App\Exports;

use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Excel export of every confirmed (paid) application in a batch, in
 * roll-number order — the official intaker list.
 */
class ConfirmedApplicantsExport extends BatchReportExport
{
    public function query()
    {
        return $this->baseQuery()
            ->with(['applicant:id,email,phone_number', 'applicant.profile:id,applicant_id,full_name,father_name,mother_name'])
            ->orderBy('roll_number');
    }

    public function headings(): array
    {
        return [
            'Roll',
            'Application ID',
            'Name',
            "Father's Name",
            "Mother's Name",
            'Mobile',
            'Email',
            'Trx ID',
            'Paid At',
        ];
    }

    public function map($application): array
    {
        $profile = $application->applicant?->profile;
        $paidAtRaw = $application->getRawOriginal('paid_at');

        return [
            $application->roll_number,
            $application->application_number,
            $profile?->full_name,
            $profile?->father_name,
            $profile?->mother_name,
            $application->applicant?->phone_number,
            $application->applicant?->email,
            $application->trx_id,
            $paidAtRaw ? Carbon::parse($paidAtRaw)->format('d M Y, h:i A') : null,
        ];
    }

    public function title(): string
    {
        return 'Confirmed Applicants';
    }

    protected function reportName(): string
    {
        return 'Confirmed Applicants';
    }

    protected function summaryLine(): string
    {
        return "Total confirmed: {$this->totalRows}";
    }

    protected function centeredColumns(): array
    {
        return ['A'];
    }

    protected function baseQuery(): Builder
    {
        return Application::query()
            ->where('batch_id', $this->batch->id)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->whereNotNull('roll_number');
    }
}
