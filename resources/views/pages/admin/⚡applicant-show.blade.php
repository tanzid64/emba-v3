<?php

use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Applicant Details')]
#[Layout('layouts.app')]
class extends Component {
    public Application $application;

    public function mount(Application $application): void
    {
        $this->application = $application->load([
            'batch',
            'applicant.profile',
            'applicant.addresses.district',
            'applicant.addresses.upazila',
            'applicant.educationHistories',
            'applicant.expHistories',
        ]);
    }

    public function applicationStatusColor(?ApplicationStatusEnum $status): string
    {
        return match ($status) {
            ApplicationStatusEnum::COMPLETED => 'green',
            ApplicationStatusEnum::AWAITING_PAYMENT => 'yellow',
            ApplicationStatusEnum::PENDING => 'blue',
            default => 'zinc',
        };
    }

    public function paymentStatusColor(?PaymentStatusEnum $status): string
    {
        return match ($status) {
            PaymentStatusEnum::PAID, PaymentStatusEnum::COMPLETED => 'green',
            PaymentStatusEnum::PENDING => 'yellow',
            PaymentStatusEnum::FAILED => 'red',
            default => 'zinc',
        };
    }
}; ?>

@php
    $applicant = $application->applicant;
    $profile = $applicant?->profile;
    $present = $applicant?->addresses->firstWhere('type', \App\Enums\AddressTypeEnum::PRESENT);
    $permanent = $applicant?->addresses->firstWhere('type', \App\Enums\AddressTypeEnum::PERMANENT);
    $educations = $applicant?->educationHistories?->sortBy('id') ?? collect();
    $experiences = $applicant?->expHistories?->sortBy('id') ?? collect();
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-4">
            <img
                src="{{ $profile?->photo_url ?? asset('assets/images/default-avatar.png') }}"
                alt=""
                class="size-20 rounded-xl object-cover bg-zinc-100 border border-zinc-200"
            />
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-brand">
                    {{ $application->batch?->name ?? __('—') }} · {{ $application->batch?->code }}
                </p>
                <h1 class="text-2xl font-bold text-zinc-900 uppercase">
                    {{ $profile?->full_name ?? __('Profile not completed') }}
                </h1>
                <p class="font-mono text-sm text-zinc-600 mt-1">{{ $application->application_number }}</p>

                <div class="flex items-center gap-2 mt-3 flex-wrap">
                    <x-ui.badge :color="$this->applicationStatusColor($application->status)" size="sm">
                        {{ __('Status') }}: {{ $application->status?->label() ?? '—' }}
                    </x-ui.badge>
                    <x-ui.badge :color="$this->paymentStatusColor($application->payment_status)" size="sm">
                        {{ __('Payment') }}: {{ $application->payment_status?->label() ?? '—' }}
                    </x-ui.badge>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <x-ui.button variant="ghost" icon="arrow-left" :href="route('admin.applicants.index')" wire:navigate>
                {{ __('Back') }}
            </x-ui.button>
            <x-ui.button variant="outline" icon="file-text">
                {{ __('View Application Form') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="download">
                {{ __('Download PDF') }}
            </x-ui.button>
        </div>
    </div>

    @php
        $card = 'rounded-xl border border-zinc-200 bg-white p-6';
        $sectionTitle = 'text-sm font-bold text-zinc-900';
        $sectionSubtitle = 'text-xs text-zinc-500 mt-0.5';
        $labelClasses = 'block text-[10px] font-bold uppercase tracking-widest text-zinc-500';
        $valueClasses = 'text-sm font-medium text-zinc-800 mt-1';
        $valueMuted = 'text-sm italic text-zinc-400 mt-1';
    @endphp

    {{-- ===================== APPLICATION & PAYMENT ===================== --}}
    <div class="{{ $card }}">
        <div class="flex items-start gap-3 mb-5 pb-4 border-b border-zinc-100">
            <span class="inline-flex items-center justify-center size-9 rounded-lg bg-brand text-white shrink-0">
                <x-lucide-wallet class="size-4" />
            </span>
            <div>
                <h2 class="{{ $sectionTitle }}">{{ __('Application & Payment') }}</h2>
                <p class="{{ $sectionSubtitle }}">{{ __('Submission and transaction summary for this batch.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
            <div>
                <span class="{{ $labelClasses }}">{{ __('Application No.') }}</span>
                <p class="{{ $valueClasses }} font-mono">{{ $application->application_number }}</p>
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Status') }}</span>
                <p class="{{ $valueClasses }}">{{ $application->status?->label() ?? '—' }}</p>
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Submitted At') }}</span>
                @if ($application->applied_at)
                    <p class="{{ $valueClasses }}">{{ $application->applied_at['formatted'] ?? '—' }}</p>
                @else
                    <p class="{{ $valueMuted }}">{{ __('Not submitted') }}</p>
                @endif
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Amount') }}</span>
                <p class="{{ $valueClasses }}">৳ {{ number_format((float) $application->amount, 2) }}</p>
            </div>

            <div>
                <span class="{{ $labelClasses }}">{{ __('Payment Status') }}</span>
                <p class="{{ $valueClasses }}">{{ $application->payment_status?->label() ?? '—' }}</p>
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Payment Method') }}</span>
                <p class="{{ $valueClasses }}">{{ $application->payment_method?->label() ?? '—' }}</p>
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Trx ID') }}</span>
                @if ($application->trx_id)
                    <p class="{{ $valueClasses }} font-mono">{{ $application->trx_id }}</p>
                @else
                    <p class="{{ $valueMuted }}">—</p>
                @endif
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Payment ID') }}</span>
                @if ($application->payment_id)
                    <p class="{{ $valueClasses }} font-mono">{{ $application->payment_id }}</p>
                @else
                    <p class="{{ $valueMuted }}">—</p>
                @endif
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Paid At') }}</span>
                @if ($application->paid_at)
                    <p class="{{ $valueClasses }}">{{ \Carbon\Carbon::parse($application->paid_at)->format('d M Y - h:i A') }}</p>
                @else
                    <p class="{{ $valueMuted }}">—</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== CONTACT ===================== --}}
    <div class="{{ $card }}">
        <div class="flex items-start gap-3 mb-5 pb-4 border-b border-zinc-100">
            <span class="inline-flex items-center justify-center size-9 rounded-lg bg-brand text-white shrink-0">
                <x-lucide-mail class="size-4" />
            </span>
            <div>
                <h2 class="{{ $sectionTitle }}">{{ __('Contact') }}</h2>
                <p class="{{ $sectionSubtitle }}">{{ __('Email and phone number provided during registration.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <span class="{{ $labelClasses }}">{{ __('Email') }}</span>
                <p class="{{ $valueClasses }} break-all">{{ $applicant?->email ?? '—' }}</p>
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Phone') }}</span>
                <p class="{{ $valueClasses }}">{{ $applicant?->phone_number ?? '—' }}</p>
            </div>
            <div>
                <span class="{{ $labelClasses }}">{{ __('Email Verified') }}</span>
                @if ($applicant?->email_verified_at)
                    <p class="{{ $valueClasses }}">{{ $applicant->email_verified_at['formatted'] ?? '—' }}</p>
                @else
                    <p class="{{ $valueMuted }}">{{ __('Not verified') }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== PROFILE ===================== --}}
    @if ($profile)
        <div class="{{ $card }}">
            <div class="flex items-start gap-3 mb-5 pb-4 border-b border-zinc-100">
                <span class="inline-flex items-center justify-center size-9 rounded-lg bg-brand text-white shrink-0">
                    <x-lucide-user-circle class="size-4" />
                </span>
                <div>
                    <h2 class="{{ $sectionTitle }}">{{ __('Applicant Profile') }}</h2>
                    <p class="{{ $sectionSubtitle }}">{{ __('Personal details and demographics.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                <div>
                    <span class="{{ $labelClasses }}">{{ __('Full Name') }}</span>
                    <p class="{{ $valueClasses }} uppercase">{{ $profile->full_name }}</p>
                </div>
                <div>
                    <span class="{{ $labelClasses }}">{{ __('Date of Birth') }}</span>
                    <p class="{{ $valueClasses }}">{{ optional($profile->date_of_birth)->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <span class="{{ $labelClasses }}">{{ __("Father's Name") }}</span>
                    <p class="{{ $valueClasses }}">{{ $profile->father_name }}</p>
                </div>
                <div>
                    <span class="{{ $labelClasses }}">{{ __("Mother's Name") }}</span>
                    <p class="{{ $valueClasses }}">{{ $profile->mother_name }}</p>
                </div>

                <div>
                    <span class="{{ $labelClasses }}">{{ __('Gender') }}</span>
                    <p class="{{ $valueClasses }}">{{ $profile->gender?->label() ?? '—' }}</p>
                </div>
                <div>
                    <span class="{{ $labelClasses }}">{{ __('Blood Group') }}</span>
                    <p class="{{ $valueClasses }}">{{ $profile->blood_group?->label() ?? '—' }}</p>
                </div>
                <div>
                    <span class="{{ $labelClasses }}">{{ __('Religion') }}</span>
                    <p class="{{ $valueClasses }}">{{ $profile->religion?->label() ?? '—' }}</p>
                </div>
                <div>
                    <span class="{{ $labelClasses }}">{{ __('Marital Status') }}</span>
                    <p class="{{ $valueClasses }}">{{ $profile->marital_status?->label() ?? '—' }}</p>
                </div>

                <div>
                    <span class="{{ $labelClasses }}">{{ __('Nationality') }}</span>
                    <p class="{{ $valueClasses }}">{{ $profile->nationality }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="{{ $card }}">
            <p class="text-sm italic text-zinc-400">{{ __('Profile has not been completed by the applicant.') }}</p>
        </div>
    @endif

    {{-- ===================== ADDRESSES ===================== --}}
    <div class="{{ $card }}">
        <div class="flex items-start gap-3 mb-5 pb-4 border-b border-zinc-100">
            <span class="inline-flex items-center justify-center size-9 rounded-lg bg-brand text-white shrink-0">
                <x-lucide-map-pin class="size-4" />
            </span>
            <div>
                <h2 class="{{ $sectionTitle }}">{{ __('Addresses') }}</h2>
                <p class="{{ $sectionSubtitle }}">{{ __('Present and permanent address.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach ([['label' => __('Present Address'), 'address' => $present], ['label' => __('Permanent Address'), 'address' => $permanent]] as $row)
                <div class="rounded-lg border border-zinc-200 p-4">
                    <p class="{{ $labelClasses }} mb-2">{{ $row['label'] }}</p>
                    @if ($row['address'])
                        <p class="text-sm text-zinc-800 leading-relaxed">
                            @if ($row['address']->care)
                                <span class="text-zinc-500">{{ __('C/O') }}:</span> {{ $row['address']->care }}<br />
                            @endif
                            @if ($row['address']->road)
                                {{ $row['address']->road }}<br />
                            @endif
                            @if ($row['address']->post_office)
                                {{ __('P.O.') }}: {{ $row['address']->post_office }}
                                @if ($row['address']->postal_code) - {{ $row['address']->postal_code }} @endif
                                <br />
                            @endif
                            @if ($row['address']->upazila)
                                {{ $row['address']->upazila->name }},
                            @endif
                            @if ($row['address']->district)
                                {{ $row['address']->district->name }}
                            @endif
                        </p>
                    @else
                        <p class="text-sm italic text-zinc-400">{{ __('Not provided.') }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===================== EDUCATION ===================== --}}
    @php
        $totalSchooling = (float) ($profile?->tot_year_of_schooling ?? 0);
    @endphp
    <x-ui.table>
        <x-slot:toolbar>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-start gap-3">
                    <span class="inline-flex items-center justify-center size-9 rounded-lg bg-brand text-white shrink-0">
                        <x-lucide-graduation-cap class="size-4" />
                    </span>
                    <div>
                        <h2 class="{{ $sectionTitle }}">{{ __('Education History') }}</h2>
                        <p class="{{ $sectionSubtitle }}">{{ __('Academic qualifications and results.') }}</p>
                    </div>
                </div>
                <div class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5">
                    <x-lucide-clock class="size-4 text-zinc-400" />
                    <div class="leading-tight">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">{{ __('Total Years of Schooling') }}</p>
                        <p class="text-sm font-bold text-zinc-800 tabular-nums">
                            {{ rtrim(rtrim(number_format($totalSchooling, 2, '.', ''), '0'), '.') }}
                            {{ $totalSchooling === 1.0 ? __('Year') : __('Years') }}
                        </p>
                    </div>
                </div>
            </div>
        </x-slot:toolbar>

        <x-slot:columns>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Degree') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Major / Subject') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Institute') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Result') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Duration') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Passing Year') }}</th>
            </x-slot:columns>

            @forelse ($educations as $edu)
                <tr class="hover:bg-zinc-50/60 transition-colors">
                    <td class="px-4 py-3 font-medium text-zinc-900">{{ $edu->name }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ $edu->major }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ $edu->institute }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ $edu->result }} / {{ $edu->scale }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ $edu->duration }} {{ __('yrs') }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ $edu->passing_year }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-zinc-500 italic">{{ __('No education records.') }}</td>
                </tr>
            @endforelse
    </x-ui.table>

    {{-- ===================== EXPERIENCE ===================== --}}
    @php
        $totalExperience = (float) ($profile?->tot_year_of_exp ?? 0);
    @endphp
    <x-ui.table>
        <x-slot:toolbar>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-start gap-3">
                    <span class="inline-flex items-center justify-center size-9 rounded-lg bg-brand text-white shrink-0">
                        <x-lucide-briefcase class="size-4" />
                    </span>
                    <div>
                        <h2 class="{{ $sectionTitle }}">{{ __('Experience History') }}</h2>
                        <p class="{{ $sectionSubtitle }}">{{ __('Professional work history.') }}</p>
                    </div>
                </div>
                <div class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5">
                    <x-lucide-clock class="size-4 text-zinc-400" />
                    <div class="leading-tight">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">{{ __('Total Years of Experience') }}</p>
                        <p class="text-sm font-bold text-zinc-800 tabular-nums">
                            {{ rtrim(rtrim(number_format($totalExperience, 2, '.', ''), '0'), '.') }}
                            {{ $totalExperience === 1.0 ? __('Year') : __('Years') }}
                        </p>
                    </div>
                </div>
            </div>
        </x-slot:toolbar>

        <x-slot:columns>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Organization') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Designation') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Duration') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-4 py-3">{{ __('Total Years') }}</th>
            </x-slot:columns>

            @forelse ($experiences as $exp)
                <tr class="hover:bg-zinc-50/60 transition-colors">
                    <td class="px-4 py-3 font-medium text-zinc-900">{{ $exp->organization }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ $exp->designation }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ $exp->duration }}</td>
                    <td class="px-4 py-3 text-right text-zinc-700 tabular-nums">{{ number_format((float) $exp->total_experience, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-zinc-500 italic">{{ __('No experience records.') }}</td>
                </tr>
            @endforelse
    </x-ui.table>
</div>
