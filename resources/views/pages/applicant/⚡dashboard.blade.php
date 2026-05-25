<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')]
#[Layout('layouts.applicant.app')]
class extends Component {
    public \Illuminate\Support\Collection $steps;

    private function formatDate(mixed $date): ?string
    {
        if (is_null($date)) {
            return null;
        }

        if (is_array($date)) {
            return $date['formatted'];
        }

        return $date->format('d M, Y - h:i A');
    }

    public function mount(): void
    {
        $applicant = auth('applicant')->user()->load(['profile', 'applications']);

        $this->steps = collect(config('application_steps'))->map(function ($step) use ($applicant) {
            [$completed, $completedAt] = match ($step['model']) {
                'App\Models\Applicant' => [
                    true,
                    $this->formatDate($applicant->created_at),
                ],
                'App\Models\ApplicantProfile' => [
                    $applicant->profile !== null,
                    $this->formatDate($applicant->profile?->created_at),
                ],
                'App\Models\Application' => [
                    $applicant->applications->isNotEmpty(),
                    $this->formatDate($applicant->applications->first()?->created_at),
                ],
                default => [false, null],
            };

            return [
                ...$step,
                'completed'   => $completed,
                'completedAt' => $completedAt,
            ];
        });
    }
}; ?>

<div>
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Applicant Portal</p>
        <h1 class="font-inter font-bold text-2xl text-gray-900">Application Dashboard</h1>
        <p class="text-gray-400 text-sm mt-1">Welcome back, {{ auth('applicant')->user()->email }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col gap-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#2F1B72;">
                <x-lucide-file-text class="size-5" />
            </div>
            <h3 class="font-inter font-bold text-gray-800">Application Status</h3>
            <p class="text-sm text-gray-500">Your application has not been started yet.</p>
            <span class="inline-block text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full w-fit" style="background:#f4f4f8; color:#8b072b;">Not Started</span>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col gap-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#58b325;">
                <x-lucide-mail-check class="size-5" />
            </div>
            <h3 class="font-inter font-bold text-gray-800">Email Verified</h3>
            @php $verifiedAt = auth('applicant')->user()->email_verified_at; @endphp
            @if ($verifiedAt)
                <p class="text-sm text-gray-500">{{ $verifiedAt['formatted'] }}</p>
                <span class="inline-block text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full w-fit" style="background:#f0fde8; color:#3a7e14;">Verified</span>
            @else
                <p class="text-sm text-gray-500">Your email has not been verified yet.</p>
                <span class="inline-block text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full w-fit" style="background:#fef3c7; color:#92400e;">Pending</span>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col gap-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#A27126;">
                <x-lucide-info class="size-5" />
            </div>
            <h3 class="font-inter font-bold text-gray-800">Admission Circular</h3>
            <p class="text-sm text-gray-500">FBS EMBA 8th Batch applications are now open.</p>
            <a href="#" class="text-xs font-bold hover:underline" style="color:#2F1B72;">View Circular &rarr;</a>
        </div>

    </div>

    <div class="mt-5 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <div class="mb-6">
            <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Your Progress</p>
            <h2 class="font-inter font-bold text-lg text-gray-900">Application Flow</h2>
            <p class="text-gray-400 text-sm mt-1">Track each step of your admission application.</p>
        </div>

        <ol class="relative max-w-2xl">
            @foreach ($steps as $index => $step)
                @php $isLast = $loop->last; @endphp
                <li class="flex gap-5 {{ $isLast ? '' : 'pb-8' }} relative">

                    {{-- Connector line --}}
                    @unless ($isLast)
                        <div class="absolute left-[19px] top-10 bottom-0 w-px"
                             style="background: {{ $step['completed'] ? '#58b325' : '#e5e7eb' }};"></div>
                    @endunless

                    {{-- Icon --}}
                    <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center z-10 transition-colors"
                         style="background: {{ $step['completed'] ? '#58b325' : '#f3f4f6' }};">
                        @if ($step['completed'])
                            <x-lucide-check class="size-4 text-white" />
                        @else
                            <x-dynamic-component
                                :component="'lucide-' . $step['icon']"
                                class="size-4 text-gray-400"
                            />
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0 pt-1.5">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <h3 class="font-inter font-bold text-sm text-gray-800">{{ $step['title'] }}</h3>
                            @if ($step['completed'])
                                <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full"
                                      style="background:#f0fde8; color:#3a7e14;">
                                    <x-lucide-check class="size-3" /> Complete
                                </span>
                            @else
                                <span class="inline-flex text-xs font-bold px-2 py-0.5 rounded-full"
                                      style="background:#f4f4f8; color:#8b072b;">
                                    Pending
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 leading-relaxed">{{ $step['description'] }}</p>
                        @if ($step['completedAt'])
                            <p class="text-xs text-gray-400 mt-1.5">
                                <x-lucide-clock class="size-3 inline -mt-0.5" /> {{ $step['completedAt'] }}
                            </p>
                        @endif
                    </div>

                </li>
            @endforeach
        </ol>
    </div>

    <div class="mt-5 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="font-inter font-bold text-gray-800 mb-1">Ready to begin your application?</h3>
            <p class="text-sm text-gray-500">Complete your profile and submit your application documents.</p>
        </div>
        <a href="{{ route('applicant.application') }}" class="shrink-0 inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90" style="background:#8b072b;">
            Submit Application <x-lucide-arrow-right class="size-4" />
        </a>
    </div>
</div>
