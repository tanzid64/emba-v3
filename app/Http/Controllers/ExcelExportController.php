<?php

namespace App\Http\Controllers;

use App\Exports\ConfirmedApplicantsExport;
use App\Exports\ExamResultsExport;
use App\Exports\VivaShortlistExport;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExcelExportController extends Controller
{
    /**
     * Stream the exam-results workbook for a batch as an .xlsx download.
     * Admin-only. Reads three optional query params used by ExamResultsExport
     * to filter the rows:
     *
     *   status      — "Passed" / "Failed" (omit / blank = all)
     *   merit_from  — minimum merit_position (inclusive)
     *   merit_to    — maximum merit_position (inclusive)
     */
    public function examResults(Request $request, Batch $batch)
    {
        $this->ensureAdmin($request);

        $status = $this->normaliseString($request->query('status'));
        $meritFrom = $this->normaliseInt($request->query('merit_from'));
        $meritTo = $this->normaliseInt($request->query('merit_to'));

        $filename = 'exam-results-'.$batch->code.'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new ExamResultsExport(
                batch: $batch,
                statusFilter: $status,
                meritFrom: $meritFrom,
                meritTo: $meritTo,
            ),
            $filename,
        );
    }

    /**
     * Stream the confirmed (paid) applicants workbook for a batch as an
     * .xlsx download. Admin-only. Exports the full intaker list in
     * roll-number order.
     */
    public function confirmedApplicants(Request $request, Batch $batch)
    {
        $this->ensureAdmin($request);

        $filename = 'confirmed-applicants-'.$batch->code.'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new ConfirmedApplicantsExport($batch), $filename);
    }

    /**
     * Stream the viva shortlist workbook for a batch as an .xlsx download.
     * Admin-only. Exports every candidate whose MCQ mark reaches the
     * batch's eligibility cutoff.
     */
    public function vivaShortlist(Request $request, Batch $batch)
    {
        $this->ensureAdmin($request);

        $batch->loadMissing('admissionSetting');

        $filename = 'viva-shortlist-'.$batch->code.'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new VivaShortlistExport($batch), $filename);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user() instanceof User, 403);
    }

    private function normaliseString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === null || $value === '' ? null : $value;
    }

    private function normaliseInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
