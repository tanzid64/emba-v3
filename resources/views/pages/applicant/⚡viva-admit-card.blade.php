<?php

use App\Models\AdmissionResult;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Viva Admit Card')]
#[Layout('layouts.applicant.app')]
class extends Component {
    public Applicant $applicant;

    public ?Application $application = null;

    public ?AdmissionSetting $admissionSetting = null;

    public ?AdmissionResult $result = null;

    public function mount(): void
    {
        $this->applicant = auth('applicant')->user()->load([
            'profile',
            'batch.admissionSetting',
        ]);

        $this->application = $this->applicant->applications()
            ->where('batch_id', $this->applicant->batch_id)
            ->with('vivaBoard')
            ->first();

        $this->admissionSetting = $this->applicant->batch?->admissionSetting;

        $this->result = AdmissionResult::where('batch_id', $this->applicant->batch_id)
            ->where('applicant_id', $this->applicant->id)
            ->first();
    }

    public function vivaThreshold(): float
    {
        return (float) ($this->admissionSetting?->viva_mcq_threshold
            ?? config('result.viva_mcq_threshold'));
    }

    public function isVivaAdmitCardPublished(): bool
    {
        return (bool) ($this->admissionSetting?->is_viva_admit_card_published);
    }

    public function isVivaEligible(): bool
    {
        return $this->result !== null
            && (float) $this->result->mcq_marks >= $this->vivaThreshold();
    }

    public function hasBoard(): bool
    {
        return $this->application?->viva_board_id !== null;
    }
}; ?>

<div>
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Admission</p>
        <h1 class="font-inter font-bold text-2xl text-gray-900">Viva Admit Card</h1>
        <p class="text-gray-400 text-sm mt-1">Your viva voce hall ticket.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#2F1B72;">
                <x-lucide-ticket class="size-5" />
            </div>
            <div>
                <h2 class="font-inter font-bold text-lg text-gray-900">Viva Admit Card</h2>
                <p class="text-xs text-gray-500 mt-0.5">Viva voce hall ticket for {{ $applicant->batch?->name ?? '—' }}</p>
            </div>
        </div>

        @if (! $this->isVivaAdmitCardPublished())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3">
                <x-lucide-info class="size-5 text-amber-600 mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold text-amber-900">Viva admit card is not published yet</p>
                    <p class="text-sm text-amber-700 mt-1">
                        Your viva admit card will be available here once published by the administration. Please check back later.
                    </p>
                </div>
            </div>
        @elseif (! $this->isVivaEligible())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 flex items-start gap-3">
                <x-lucide-circle-alert class="size-5 text-rose-600 mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold text-rose-900">Viva admit card not available</p>
                    <p class="text-sm text-rose-700 mt-1">
                        Only candidates shortlisted for the viva (based on the MCQ cutoff) can download a viva admit card.
                    </p>
                </div>
            </div>
        @elseif (! $this->hasBoard() || ! $application)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3">
                <x-lucide-info class="size-5 text-amber-600 mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold text-amber-900">Your viva board is not assigned yet</p>
                    <p class="text-sm text-amber-700 mt-1">
                        You are shortlisted for the viva. Your viva admit card will be available once your viva board is assigned.
                    </p>
                </div>
            </div>
        @else
            <div class="space-y-5">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 flex items-start gap-3">
                    <x-lucide-circle-check class="size-5 text-emerald-600 mt-0.5 shrink-0" />
                    <div class="flex-1">
                        <p class="font-semibold text-emerald-900">Your viva admit card is ready</p>
                        <p class="text-sm text-emerald-700 mt-1">Download and print your viva admit card before the viva voce.</p>
                    </div>
                    <a href="{{ route('pdf.viva-admit-card', ['appNo' => $application->application_number, 'action' => 'download']) }}"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold text-white shrink-0"
                        style="background:#2F1B72;"
                    >
                        <x-lucide-download class="size-4" />
                        Download
                    </a>
                </div>

                {{-- Viva details --}}
                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500">Roll Number</p>
                        <p class="mt-1 font-bold text-gray-900">{{ $application->roll_number ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500">Date of Viva</p>
                        <p class="mt-1 font-bold text-gray-900">
                            @if ($admissionSetting?->viva_date)
                                {{ is_array($admissionSetting->viva_date) ? $admissionSetting->viva_date['formatted'] : $admissionSetting->viva_date }}
                            @else
                                To be announced
                            @endif
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500">Viva Board</p>
                        <p class="mt-1 font-bold text-gray-900">{{ $application->vivaBoard?->board_name ?? '—' }}</p>
                        @if ($application->vivaBoard?->center_name || $application->vivaBoard?->room_name)
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $application->vivaBoard?->center_name }}
                                @if ($application->vivaBoard?->room_name)
                                    · {{ $application->vivaBoard->room_name }}
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
