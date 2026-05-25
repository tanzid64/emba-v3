<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\District;
use App\Models\Upazila;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class PDFController extends Controller
{
    /**
     * Stream (or download) the applicant's application form as a PDF.
     * Visible to the applicant who owns the record and to any admin user.
     */
    public function generateApplicationFormPDF(Application $application, Request $request)
    {
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
            'title' => "EMBA Application Form {$application->application_number}",
        ]);

        return $request->input('action') === 'download'
            ? $pdf->download($filename)
            : $pdf->stream($filename);
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
}
