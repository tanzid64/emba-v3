<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\District;
use App\Models\ExamCenter;
use App\Models\Payment;
use App\Models\Upazila;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class PDFController extends Controller
{
    /**
     * Stream (or download) the applicant's application form as a PDF.
     * Visible to the applicant who owns the record and to any admin user.
     */
    public function generateApplicationFormPDF(string $appNo, Request $request)
    {
        $application = Application::where('application_number', $appNo)->firstOrFail();
        $this->ensureCanAccess($request, $application->applicant_id);

        $application->load([
            'batch',
            'applicant.profile',
            'applicant.addresses',
            'applicant.educationHistories',
            'applicant.expHistories',
        ]);

        [$data, $districts, $upazilas] = $this->buildApplicationFormData($application);

        $filename = "EMBA_APPLICATION_FORM_{$application->application_number}.pdf";

        $pdf = PDF::loadView('pdfs.application-form', compact('data', 'districts', 'upazilas'), [], [
            'title' => "EMBA_Application_Form_{$application->application_number}",
        ]);

        return $request->input('action') === 'download'
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Stream (or download) a bKash payment receipt as a PDF.
     * Visible to the applicant who owns the payment and to any admin user.
     */
    public function generatePaymentReceiptPDF(string $paymentNo, Request $request)
    {
        $payment = Payment::where('payment_number', $paymentNo)
            ->with(['applicant.profile', 'applicant.batch'])
            ->firstOrFail();

        $this->ensureCanAccess($request, $payment->applicant_id);

        $receiptNo = $payment->payment_number;
        $purpose = $payment->actor_table; // PaymentActorEnum — exposes ->label()
        // Permanent signed URL printed as a QR on the receipt — scanning
        // takes anyone to the public verification page.
        $verifyUrl = URL::signedRoute('verify.payment', [
            'paymentNo' => $payment->payment_number,
        ]);

        $filename = "EMBA_PAYMENT_RECEIPT_{$payment->payment_number}.pdf";

        $pdf = PDF::loadView('pdfs.payment-receipt', compact('payment', 'receiptNo', 'purpose', 'verifyUrl'), [], [
            'title' => "EMBA_Payment_Receipt_{$payment->payment_number}",
        ]);

        return $request->input('action') === 'download'
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    public function generateAdmitCardPDF(string $appNo, Request $request)
    {
        $application = Application::where('application_number', $appNo)
            ->with(['batch.admissionSetting', 'examCenter', 'applicant.profile'])
            ->firstOrFail();

        $this->ensureCanAccess($request, $application->applicant_id);

        $profile = $application->applicant?->profile;

        $student = (object) [
            'application_id' => $application->application_number,
            'full_name' => $profile?->full_name,
            'father_name' => $profile?->father_name,
            'mother_name' => $profile?->mother_name,
            'mobile' => $application->applicant?->phone_number,
            'photo_path' => $profile?->photo_path,
        ];

        // Blade reads `$rollAssignment->roll` and `$rollAssignment->examCenter->*`;
        // Application already carries both — expose via a small shim.
        $rollAssignment = (object) [
            'roll' => $application->roll_number,
            'examCenter' => $application->examCenter,
        ];

        $batch = $application->batch;

        // Permanent signed URL printed as a QR on the admit card — reuses the
        // same public verifier as the application form.
        $verifyUrl = URL::signedRoute('verify.application', [
            'appNo' => $application->application_number,
        ]);

        $filename = "EMBA_ADMIT_CARD_{$application->roll_number}.pdf";

        $pdf = PDF::loadView('pdfs.admit-card', compact('student', 'rollAssignment', 'batch', 'verifyUrl'), [], [
            'title' => "EMBA_Admit_Card_{$application->roll_number}",
        ]);

        return $request->input('action') === 'download'
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Attendance sheet for a single room (exam center). Admin-only.
     */
    public function generateAttendanceSheet(int $centerId, Request $request)
    {
        $this->ensureAdmin($request);

        $center = ExamCenter::with('batch')->findOrFail($centerId);
        $students = $this->roomAssignments($centerId);

        $filename = "attendance-sheet-{$center->id}.pdf";

        $pdf = PDF::loadView('pdfs.attendance-sheet', compact('center', 'students'), [], [
            'title' => "Attendance Sheet - {$center->center_name} - {$center->room_name}",
        ]);

        return $request->input('action') === 'download'
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Attendance sheets for every room in a batch — grouped by center.
     * Admin-only.
     */
    public function generateAllAttendanceSheets(int $batchId, Request $request)
    {
        $this->ensureAdmin($request);

        $batch = Batch::findOrFail($batchId);

        $centers = ExamCenter::where('batch_id', $batchId)
            ->orderBy('center_no')
            ->orderBy('room_name')
            ->get()
            ->groupBy('center_no')
            ->map(function ($group) {
                $rooms = $group->map(function ($center) {
                    $students = $this->roomAssignments($center->id);
                    $center->students = $students;
                    $center->student_count = $students->count();

                    return $center;
                })->filter(fn ($c) => $c->student_count > 0)->values();

                return [
                    'center_no' => $group->first()->center_no,
                    'center_name' => $group->first()->center_name,
                    'rooms' => $rooms,
                ];
            })
            ->filter(fn ($group) => $group['rooms']->count() > 0)
            ->values();

        $filename = "attendance-sheet-all-{$batch->code}.pdf";

        $pdf = PDF::loadView('pdfs.attendance-sheet-all', compact('centers', 'batch'), [], [
            'title' => "Attendance Sheet - All Centers - {$batch->code}",
        ]);

        return $request->input('action') === 'download'
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Seat labels for every confirmed applicant in a batch. Admin-only.
     */
    public function generateSeatLabels(int $batchId, Request $request)
    {
        $this->ensureAdmin($request);

        $batch = Batch::findOrFail($batchId);

        $assignments = Application::query()
            ->where('batch_id', $batchId)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->whereNotNull('roll_number')
            ->with('applicant.profile:id,applicant_id,full_name,photo')
            ->orderBy('roll_number')
            ->get()
            ->map(fn (Application $app) => $this->assignmentShim($app));

        // Heavy regex backtracking can blow the default limit on large batches.
        ini_set('pcre.backtrack_limit', '50000000');

        $filename = "seat-labels-{$batch->code}.pdf";

        $pdf = PDF::loadView('pdfs.seat-labels', compact('assignments', 'batch'), [], [
            'title' => "Seat Labels - {$batch->code}",
        ]);

        return $request->input('action') === 'download'
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Confirmed applicants assigned to a room, shaped to look like v2's
     * RollAssignment so the imported templates render without edits.
     */
    private function roomAssignments(int $centerId): Collection
    {
        return Application::query()
            ->where('exam_center_id', $centerId)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->whereNotNull('roll_number')
            ->with('applicant.profile:id,applicant_id,full_name,photo')
            ->orderBy('roll_number')
            ->get()
            ->map(fn (Application $app) => $this->assignmentShim($app));
    }

    /**
     * Shape an Application to match the v2 template's
     * `$assignment->roll` and `$assignment->student->*` contract.
     *
     * `photo_path` is the absolute storage path from
     * ApplicantProfile::getPhotoPathAttribute() — same strategy the
     * application-form PDF uses, so it survives without `storage:link`.
     */
    private function assignmentShim(Application $app): object
    {
        $profile = $app->applicant?->profile;

        return (object) [
            'roll' => $app->roll_number,
            'student' => (object) [
                'full_name' => $profile?->full_name,
                'photo_path' => $profile?->photo_path,
                'mobile' => $app->applicant?->phone_number,
            ],
        ];
    }

    /**
     * Compose the flat DTO the blade template consumes via `$data->*`,
     * along with the district / upazila lookup tables it reads inline.
     *
     * @return array{0: object, 1: array<int, string>, 2: array<int, string>}
     */
    private function buildApplicationFormData(Application $application): array
    {
        $applicant = $application->applicant;
        $profile = $applicant?->profile;

        // Order present-first / permanent-last so the blade's
        // ->first() / ->last() fallbacks land on the right address.
        $addresses = ($applicant?->addresses ?? collect())
            ->sortBy(fn ($a) => $a->type?->value === 'present' ? 0 : 1)
            ->values();

        $appliedAtRaw = $application->getRawOriginal('applied_at');

        $data = (object) [
            'application_id' => $application->application_number,
            'applied_at' => $appliedAtRaw ? Carbon::parse($appliedAtRaw) : null,
            'batch' => $application->batch,
            'photo_path' => $profile?->photo_path,

            'full_name' => $profile?->full_name,
            'father_name' => $profile?->father_name,
            'mother_name' => $profile?->mother_name,
            'date_of_birth' => $profile?->date_of_birth,
            'blood_group' => $profile?->blood_group,
            'gender' => $profile?->gender,
            'marital_status' => $profile?->marital_status,
            'religion' => $profile?->religion,
            'nationality' => $profile?->nationality,

            'mobile' => $applicant?->phone_number,
            'email' => $applicant?->email,

            'addresses' => $addresses,

            'degrees' => $applicant?->educationHistories ?? collect(),
            'total_schooling_years' => $profile?->tot_year_of_schooling,

            'experiences' => $applicant?->expHistories ?? collect(),
            'total_experience_years' => $profile?->tot_year_of_exp,

            'payment_status' => in_array(
                $application->payment_status,
                [PaymentStatusEnum::PAID, PaymentStatusEnum::COMPLETED],
                true,
            ),
            'trx_id' => $application->trx_id,
            'pay_type' => $application->payment_method,
            'paid_at' => $application->paid_at,

            // Permanent signed URL to the public verification page — printed
            // as a QR code on the form so anyone scanning a paper copy
            // lands on the "Authentic" verification view.
            'verify_url' => URL::signedRoute('verify.application', [
                'appNo' => $application->application_number,
            ]),
        ];

        $districtIds = $addresses->pluck('district_id')->filter()->unique();
        $upazilaIds = $addresses->pluck('upazila_id')->filter()->unique();

        $districts = District::whereIn('id', $districtIds)->pluck('name', 'id')->all();
        $upazilas = Upazila::whereIn('id', $upazilaIds)->pluck('name', 'id')->all();

        return [$data, $districts, $upazilas];
    }

    /**
     * Ownership policy for any applicant-owned PDF in this controller.
     * Admins (User on the `web` guard) are always allowed.
     * Applicants (Applicant on the `applicant` guard) are allowed only
     * when their id matches the resource's owning applicant id.
     */
    private function ensureCanAccess(Request $request, int $applicantId): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            return;
        }

        abort_unless(
            $user instanceof Applicant && $user->getKey() === $applicantId,
            403,
        );
    }

    /**
     * Admin-only gate for attendance sheets / seat labels. Applicants must
     * not be able to enumerate the roll list of an entire room or batch.
     */
    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user() instanceof User, 403);
    }
}
