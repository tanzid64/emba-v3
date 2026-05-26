<?php

use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\AdmissionSetting;
use App\Models\Application;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admit Card')]
#[Layout('layouts.applicant.app')]
class extends Component {
    public Applicant $applicant;

    public ?Application $application = null;

    public ?AdmissionSetting $admissionSetting = null;

    public function mount(): void
    {
        $this->applicant = auth('applicant')->user()->load([
            'profile',
            'batch.admissionSetting',
        ]);

        $this->application = $this->applicant->applications()
            ->where('batch_id', $this->applicant->batch_id)
            ->with('examCenter')
            ->first();

        $this->admissionSetting = $this->applicant->batch?->admissionSetting;
    }

    public function isAdmitCardPublished(): bool
    {
        return (bool) ($this->admissionSetting?->is_admit_card_published);
    }

    public function isPaid(): bool
    {
        return in_array(
            $this->application?->payment_status,
            [PaymentStatusEnum::PAID, PaymentStatusEnum::COMPLETED],
            true,
        );
    }
}; ?>

<div>
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Admission</p>
        <h1 class="font-inter font-bold text-2xl text-gray-900">Admit Card</h1>
        <p class="text-gray-400 text-sm mt-1">Your admission test hall ticket.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#2F1B72;">
                <x-lucide-ticket class="size-5" />
            </div>
            <div>
                <h2 class="font-inter font-bold text-lg text-gray-900">Admit Card</h2>
                <p class="text-xs text-gray-500 mt-0.5">Admission exam hall ticket for {{ $applicant->batch?->name ?? '—' }}</p>
            </div>
        </div>

        @if (! $this->isAdmitCardPublished())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3">
                <x-lucide-info class="size-5 text-amber-600 mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold text-amber-900">Admit card is not published yet</p>
                    <p class="text-sm text-amber-700 mt-1">
                        Your admit card will be available here once published by the administration. Please check back later.
                    </p>
                </div>
            </div>
        @elseif (! $this->isPaid() || ! $application)
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 flex items-start gap-3">
                <x-lucide-circle-alert class="size-5 text-rose-600 mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold text-rose-900">Admit card not available</p>
                    <p class="text-sm text-rose-700 mt-1">
                        Only confirmed applicants who have completed payment can download an admit card.
                    </p>
                </div>
            </div>
        @else
            <div class="space-y-5">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 flex items-start gap-3">
                    <x-lucide-circle-check class="size-5 text-emerald-600 mt-0.5 shrink-0" />
                    <div class="flex-1">
                        <p class="font-semibold text-emerald-900">Your admit card is ready</p>
                        <p class="text-sm text-emerald-700 mt-1">Download and print your admit card before the admission test.</p>
                    </div>
                    <a href="{{ route('pdf.admit-card', ['appNo' => $application->application_number, 'action' => 'download']) }}"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold text-white shrink-0"
                        style="background:#2F1B72;"
                    >
                        <x-lucide-download class="size-4" />
                        Download
                    </a>
                </div>

                {{-- Admit Card preview --}}
                <div class="bg-white border border-gray-300 rounded-lg p-6 sm:p-8 overflow-x-auto">
                    <div style="border:2px solid black; border-radius:5px; padding:14px; background:white; min-width:760px;">

                        {{-- Header --}}
                        <table width="100%">
                            <tr>
                                <td width="20%" align="left" style="vertical-align: top;">
                                    <img src="{{ asset('assets/logo/logo.jpg') }}" style="width:90px; height:100px;" alt="University of Dhaka" />
                                </td>
                                <td width="60%" align="center" style="vertical-align: top;">
                                    <h2 style="font-size:22px; margin:0; font-weight:bold; line-height:1.4;">
                                        Executive MBA Program<br>
                                        Faculty of Business Studies<br>
                                        University of Dhaka
                                    </h2>
                                    <h2 style="font-size:30px; font-weight:bolder; margin:12px 0 0 0;">
                                        Admit Card
                                    </h2>
                                </td>
                                <td width="20%" align="right" style="vertical-align: top;">
                                    <img src="{{ $applicant->profile?->photo_url }}"
                                        style="width:120px; height:120px; border:1px solid #000; object-fit:cover;"
                                        alt="Applicant Photo" />
                                </td>
                            </tr>
                        </table>

                        {{-- Admission Test Title --}}
                        <table width="100%" style="font-size:19px; margin-top:12px;">
                            <tr>
                                <td align="center">
                                    <h3 style="margin:10px 0;"><u>Admission Test - {{ $applicant->batch?->name ?? 'N/A' }}</u></h3>
                                </td>
                            </tr>
                        </table>

                        {{-- Applicant Info --}}
                        <table width="100%" border="1" rules="all"
                            style="font-size:18px; font-family: 'Times New Roman', Times, serif; margin-top:12px; border-collapse:collapse;">
                            <tr style="background:#CCC">
                                <td style="padding:6px 8px;">Name</td>
                                <td colspan="3" style="padding:6px 8px;">: <b>{{ $applicant->profile?->full_name ?? '—' }}</b></td>
                            </tr>
                            <tr>
                                <td style="padding:6px 8px;">Application ID</td>
                                <td style="padding:6px 8px;">: <b>{{ $application->application_number }}</b></td>
                                <td style="padding:6px 8px;">Roll No.</td>
                                <td style="padding:6px 8px;">: <b>{{ $application->roll_number ?? 'Not Generated' }}</b></td>
                            </tr>
                            <tr style="background:#CCC">
                                <td style="padding:6px 8px;">Mother's Name</td>
                                <td colspan="3" style="padding:6px 8px;">: {{ $applicant->profile?->mother_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 8px;">Father's Name</td>
                                <td style="padding:6px 8px;">: {{ $applicant->profile?->father_name ?? '—' }}</td>
                                <td style="padding:6px 8px;">Mobile</td>
                                <td style="padding:6px 8px;">: {{ $applicant->phone_number ?? '—' }}</td>
                            </tr>
                        </table>

                        {{-- Exam Info Box --}}
                        <div style="border:3px solid black; padding:10px; margin-top:18px; text-align:center;">
                            <h3 style="margin:0; padding:5px; font-size:17px;">
                                Date of Examination:
                                @if ($admissionSetting?->exam_date)
                                    {{ is_array($admissionSetting->exam_date) ? $admissionSetting->exam_date['formatted'] : $admissionSetting->exam_date }}
                                @else
                                    Not Generated
                                @endif
                            </h3>
                            <h3 style="margin:5px 0 0 0; padding:5px; font-family: 'Times New Roman', Times, serif; font-size:17px;">
                                Examination Center:
                                @if ($application->examCenter)
                                    {{ $application->examCenter->center_name }}
                                    @if ($application->examCenter->room_name)
                                        - {{ $application->examCenter->room_name }}
                                    @endif
                                    <br />
                                    Faculty of Business Studies, University of Dhaka
                                @else
                                    Not Generated<br />
                                    Faculty of Business Studies, University of Dhaka
                                @endif
                            </h3>
                        </div>

                        {{-- Instructions --}}
                        <h3 align="center" style="margin:18px 0 8px 0;">Read the following instructions carefully:</h3>
                        <ol style="font-size:14px; text-align:justify; padding-left:24px;">
                            <li style="margin-bottom:4px;"><b>Bring your admit card to the test center and show it to the invigilator when asked.</b></li>
                            <li style="margin-bottom:4px;"><b>Use only black ball point pen to darken all required circles on the answer sheet. Use of pencil is not allowed.</b></li>
                            <li style="margin-bottom:4px;"><b>Use of any electronic device such as calculator, mobile phone, digital watch or similar devices is strictly prohibited in the examination hall.</b></li>
                            <li style="margin-bottom:4px;"><b>Keep your ears completely visible at all times during the examination.</b></li>
                            <li style="margin-bottom:4px;"><b>Applicants adopting unfair means will be expelled from the admission test.</b></li>
                            <li style="margin-bottom:4px;"><b>You will not be allowed to enter the examination hall after 10 minutes from the start of the examination and leave 10 minutes before the end of the examination.</b></li>
                            <li style="margin-bottom:4px;"><b>The main gate shall be closed at 10.00 am sharp. You may only leave the examination hall 10 minutes before the end of examination.</b></li>
                        </ol>

                        <p style="margin-top:14px;"><i>Print Date: {{ now()->format('Y-m-d') }}</i></p>

                        <h5 style="padding:7px; font-size:9px; text-align:center; margin:10px 0 0 0;">
                            This Admit Card has been generated from Admission office Online Software and downloaded through internet
                        </h5>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
