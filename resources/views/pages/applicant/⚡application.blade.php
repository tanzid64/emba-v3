<?php

use App\Models\Applicant;
use App\Models\Application;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Application')]
#[Layout('layouts.applicant.app')]
class extends Component {
    public Applicant $applicant;

    public ?Application $application = null;

    public bool $profileComplete = false;

    public function mount(): void
    {
        $this->loadApplicant();
    }

    public function submitApplication(): void
    {
        $this->loadApplicant();

        if (! $this->profileComplete) {
            Toast::error(__('Complete your profile before submitting your application.'));

            return;
        }

        if ($this->application?->is_applied) {
            Toast::info(__('Your application has already been submitted.'));

            return;
        }

        $application = $this->application ?? Application::draftFor($this->applicant);
        $application->submit();

        $this->application = $application->fresh();

        Toast::success(__('Application submitted successfully. Application No. :no', [
            'no' => $this->application->application_number,
        ]));
    }

    private function loadApplicant(): void
    {
        $this->applicant = auth('applicant')->user()->load([
            'profile',
            'addresses.district',
            'addresses.upazila',
            'educationHistories',
            'expHistories',
            'batch',
        ]);

        $this->application = $this->applicant->applications()
            ->where('batch_id', $this->applicant->batch_id)
            ->first();

        $this->profileComplete = $this->applicant->profile !== null;
    }
}; ?>

<div>
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Admission</p>
        <h1 class="font-inter font-bold text-2xl text-gray-900">Application</h1>
        <p class="text-gray-400 text-sm mt-1">Please read the rules before starting your admission application.</p>
    </div>

    {{-- Application status banner (always shown) --}}
    @php
        $statusValue = $application?->status;
        $isApplied   = $application?->is_applied ?? false;

        $statusMeta = match (true) {
            $statusValue === \App\Enums\ApplicationStatusEnum::COMPLETED => [
                'label'  => 'Completed',
                'detail' => 'Your application has been finalized.',
                'icon'   => 'badge-check',
                'bg'     => '#58b325',
                'pill'   => 'bg-emerald-50 text-emerald-700',
            ],
            $statusValue === \App\Enums\ApplicationStatusEnum::AWAITING_PAYMENT => [
                'label'  => 'Submitted — Awaiting Payment',
                'detail' => 'Pay the application fee to complete your submission.',
                'icon'   => 'wallet',
                'bg'     => '#A27126',
                'pill'   => 'bg-amber-50 text-amber-700',
            ],
            $statusValue === \App\Enums\ApplicationStatusEnum::PENDING => [
                'label'  => 'Draft',
                'detail' => 'Your application has not been submitted yet.',
                'icon'   => 'file-edit',
                'bg'     => '#2F1B72',
                'pill'   => 'bg-indigo-50 text-indigo-700',
            ],
            default => [
                'label'  => 'Not Started',
                'detail' => 'Submit your application to begin the admission process.',
                'icon'   => 'circle-dashed',
                'bg'     => '#9CA3AF',
                'pill'   => 'bg-gray-100 text-gray-600',
            ],
        };
    @endphp

    <div class="mb-6 bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="shrink-0 w-12 h-12 rounded-xl flex items-center justify-center text-white" style="background:{{ $statusMeta['bg'] }};">
                <x-dynamic-component :component="'lucide-' . $statusMeta['icon']" class="size-5" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400">Application Status</p>
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-inter font-bold text-gray-900 text-base">{{ $statusMeta['label'] }}</h3>
                    <span class="inline-flex text-xs font-bold px-2 py-0.5 rounded-full {{ $statusMeta['pill'] }}">
                        {{ $statusValue?->label() ?? 'Not Started' }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">{{ $statusMeta['detail'] }}</p>
            </div>
        </div>

        @if ($application?->application_number)
            <div class="text-right">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400">Application No.</p>
                <p class="font-mono font-bold text-gray-900 text-sm">{{ $application->application_number }}</p>
                @if ($application->applied_at)
                    <p class="text-xs text-gray-400 mt-0.5">
                        Submitted {{ $application->applied_at['formatted'] ?? '' }}
                    </p>
                @endif
            </div>
        @endif
    </div>

    {{-- Profile prerequisite notice --}}
    @unless ($profileComplete)
        <div class="mb-5 bg-amber-50 border border-amber-200 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-9 h-9 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                    <x-lucide-alert-triangle class="size-4" />
                </div>
                <div>
                    <h3 class="font-inter font-bold text-sm text-amber-900">Profile incomplete</h3>
                    <p class="text-sm text-amber-800 mt-0.5">Complete your applicant profile before starting the application.</p>
                </div>
            </div>
            <a href="{{ route('applicant.profile') }}"
                class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90"
                style="background:#8b072b;"
            >
                Complete Profile <x-lucide-arrow-right class="size-4" />
            </a>
        </div>
    @endunless

    {{-- Rules card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white" style="background:#2F1B72;">
                <x-lucide-scroll-text class="size-5" />
            </div>
            <div>
                <h2 class="font-inter font-bold text-gray-900">Application Rules &amp; Guidelines</h2>
                <p class="text-sm text-gray-500">Please review carefully before you proceed.</p>
            </div>
        </div>

        @php
            $rules = [
                [
                    'icon'  => 'user-check',
                    'title' => 'Complete your profile first',
                    'body'  => 'Your applicant profile — including personal information, address, education history, and experience — must be fully completed before you can submit an application.',
                ],
                [
                    'icon'  => 'shield-alert',
                    'title' => 'Provide accurate information',
                    'body'  => 'Any false, misleading, or fabricated information may lead to immediate disqualification, cancellation of admission, or further administrative and legal action by the institution.',
                ],
                [
                    'icon'  => 'lock',
                    'title' => 'Profile is locked after submission',
                    'body'  => 'Once your application is submitted, your profile and supporting details cannot be updated. Recheck every field carefully before you submit.',
                ],
                [
                    'icon'  => 'file-check-2',
                    'title' => 'Authentic documents only',
                    'body'  => 'All academic transcripts, certificates, and supporting documents must be genuine. You may be asked to produce originals at any stage of the admission process.',
                ],
                [
                    'icon'  => 'credit-card',
                    'title' => 'Application fee is non-refundable',
                    'body'  => 'Application and admission fees, once paid, are non-refundable under any circumstances — including rejected, withdrawn, or incomplete applications.',
                ],
                [
                    'icon'  => 'calendar-clock',
                    'title' => 'Submit before the deadline',
                    'body'  => 'Late or incomplete applications will not be accepted. It is your responsibility to submit well before the published deadline of the current admission cycle.',
                ],
                [
                    'icon'  => 'gavel',
                    'title' => 'Institution reserves final authority',
                    'body'  => 'The admission committee reserves the right to accept, reject, or revoke any application at its discretion in accordance with university policy.',
                ],
            ];
        @endphp

        <ul class="space-y-4">
            @foreach ($rules as $index => $rule)
                <li class="flex gap-4 p-4 rounded-xl border border-gray-100 bg-gray-50/60">
                    <div class="shrink-0 w-9 h-9 rounded-lg bg-white border border-gray-200 flex items-center justify-center" style="color:#8b072b;">
                        <x-dynamic-component :component="'lucide-' . $rule['icon']" class="size-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2 mb-0.5">
                            <span class="text-xs font-bold text-gray-400">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="font-inter font-bold text-sm text-gray-800">{{ $rule['title'] }}</h3>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ $rule['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6 p-4 rounded-xl border border-dashed border-gray-300 bg-white">
            <p class="text-xs text-gray-500 leading-relaxed">
                <span class="font-bold text-gray-700">Disclaimer:</span>
                By submitting an application, you confirm that you have read, understood, and agreed to the rules above and to all admission policies of the University.
            </p>
        </div>
    </div>

    {{-- Current profile summary --}}
    @if ($profileComplete)
        @php
            $profile = $applicant->profile;
            $present = $applicant->addresses->firstWhere('type', \App\Enums\AddressTypeEnum::PRESENT);
            $permanent = $applicant->addresses->firstWhere('type', \App\Enums\AddressTypeEnum::PERMANENT);

            $formatAddress = fn ($address) => $address ? collect([
                $address->care,
                $address->road,
                $address->post_office,
                $address->upazila?->name,
                $address->district?->name,
                $address->postal_code,
            ])->filter()->implode(', ') : '—';

            $infoRows = [
                ['label' => 'Full name',      'value' => $profile->full_name],
                ['label' => 'Email',          'value' => $applicant->email],
                ['label' => 'Phone',          'value' => $applicant->phone_number],
                ['label' => "Father's name",  'value' => $profile->father_name],
                ['label' => "Mother's name",  'value' => $profile->mother_name],
                ['label' => 'Date of birth',  'value' => optional($profile->date_of_birth)->format('d M, Y')],
                ['label' => 'Gender',         'value' => $profile->gender?->label()],
                ['label' => 'Blood group',    'value' => $profile->blood_group?->label()],
                ['label' => 'Religion',       'value' => $profile->religion?->label()],
                ['label' => 'Marital status', 'value' => $profile->marital_status?->label()],
                ['label' => 'Nationality',    'value' => $profile->nationality],
            ];
        @endphp

        <div class="mt-5 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
            <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white" style="background:#A27126;">
                        <x-lucide-user-circle class="size-5" />
                    </div>
                    <div>
                        <h2 class="font-inter font-bold text-gray-900">Your current profile</h2>
                        <p class="text-sm text-gray-500">Review every detail carefully — this will be submitted with your application.</p>
                    </div>
                </div>
                <a href="{{ route('applicant.profile') }}"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-bold text-xs transition-colors border border-gray-200 text-gray-700 hover:bg-gray-50"
                >
                    <x-lucide-pencil class="size-3.5" /> Edit profile
                </a>
            </div>

            {{-- Identity --}}
            <div class="flex items-center gap-4 pb-5 mb-5 border-b border-gray-100">
                <img
                    src="{{ $profile->photo_url }}"
                    alt="Profile photo"
                    class="w-16 h-16 rounded-full object-cover ring-1 ring-gray-200 bg-gray-100 shrink-0"
                />
                <div class="min-w-0">
                    <h3 class="font-inter font-bold text-gray-900 truncate">{{ $profile->full_name }}</h3>
                    <p class="text-sm text-gray-500 truncate">{{ $applicant->email }}</p>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full" style="background:#f4f4f8; color:#2F1B72;">
                            <x-lucide-graduation-cap class="size-3" /> {{ number_format((float) $profile->tot_year_of_schooling, 2) }} yrs schooling
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full" style="background:#f0fde8; color:#3a7e14;">
                            <x-lucide-briefcase class="size-3" /> {{ number_format((float) $profile->tot_year_of_exp, 2) }} yrs experience
                        </span>
                    </div>
                </div>
            </div>

            {{-- Personal information --}}
            <div class="mb-6">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Personal information</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                    @foreach ($infoRows as $row)
                        <div class="flex flex-col">
                            <dt class="text-xs text-gray-400">{{ $row['label'] }}</dt>
                            <dd class="text-sm font-semibold text-gray-800">{{ $row['value'] ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Addresses --}}
            <div class="mb-6">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Address</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/60">
                        <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color:#8b072b;">Present</p>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $formatAddress($present) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/60">
                        <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color:#8b072b;">Permanent</p>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $formatAddress($permanent) }}</p>
                    </div>
                </div>
            </div>

            {{-- Education --}}
            <div class="mb-6">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Education history</p>
                @if ($applicant->educationHistories->isEmpty())
                    <p class="text-sm text-gray-400">No education records added yet.</p>
                @else
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Degree</th>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Major</th>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Institute</th>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Result</th>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Year</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($applicant->educationHistories as $education)
                                    <tr>
                                        <td class="px-4 py-2.5 font-semibold text-gray-800">{{ $education->name }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ $education->major }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ $education->institute }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ $education->result }} / {{ $education->scale }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ $education->passing_year }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Experience --}}
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Work experience</p>
                @if ($applicant->expHistories->isEmpty())
                    <p class="text-sm text-gray-400">No work experience records added yet.</p>
                @else
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Organization</th>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Designation</th>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Duration</th>
                                    <th class="px-4 py-2.5 text-left font-bold text-xs uppercase tracking-wide">Years</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($applicant->expHistories as $exp)
                                    <tr>
                                        <td class="px-4 py-2.5 font-semibold text-gray-800">{{ $exp->organization }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ $exp->designation }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ $exp->duration }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ number_format((float) $exp->total_experience, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Footer CTA --}}
    <div class="mt-5 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="font-inter font-bold text-gray-800 mb-1">
                @if ($isApplied)
                    Application submitted
                @else
                    Ready to apply?
                @endif
            </h3>
            <p class="text-sm text-gray-500">
                @if ($isApplied)
                    Your application has been submitted. You can no longer update your profile.
                @elseif ($profileComplete)
                    Your profile is complete. Recheck the details above, then submit.
                @else
                    Finish your profile, then return here to submit your application.
                @endif
            </p>
        </div>
        @if ($isApplied)
            <span class="shrink-0 inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-bold text-sm bg-emerald-50 text-emerald-700">
                <x-lucide-check-circle class="size-4" /> Submitted
            </span>
        @elseif ($profileComplete)
            <button
                type="button"
                wire:click="submitApplication"
                wire:confirm="Once submitted, your profile is locked and cannot be edited. Submit application now?"
                wire:loading.attr="disabled"
                wire:target="submitApplication"
                class="shrink-0 inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed"
                style="background:#8b072b;"
            >
                <x-lucide-loader-2 class="size-4 animate-spin" wire:loading wire:target="submitApplication" />
                <span wire:loading.remove wire:target="submitApplication" class="inline-flex items-center gap-2">
                    Submit Application <x-lucide-arrow-right class="size-4" />
                </span>
                <span wire:loading wire:target="submitApplication">Submitting…</span>
            </button>
        @else
            <a href="{{ route('applicant.profile') }}"
                class="shrink-0 inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90"
                style="background:#2F1B72;"
            >
                Go to Profile <x-lucide-arrow-right class="size-4" />
            </a>
        @endif
    </div>
</div>
