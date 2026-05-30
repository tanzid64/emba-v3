<?php

use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admission Result')]
#[Layout('layouts.applicant.app')]
class extends Component {
    public Applicant $applicant;

    public ?Application $application = null;

    public ?AdmissionSetting $admissionSetting = null;

    public ?AdmissionResult $result = null;

    public function mount(): void
    {
        $this->applicant = auth('applicant')->user()->load(['batch.admissionSetting']);

        $this->application = $this->applicant->applications()
            ->where('batch_id', $this->applicant->batch_id)
            ->first();

        $this->admissionSetting = $this->applicant->batch?->admissionSetting;

        $this->result = AdmissionResult::where('batch_id', $this->applicant->batch_id)
            ->where('applicant_id', $this->applicant->id)
            ->first();
    }

    public function isResultPublished(): bool
    {
        return (bool) ($this->admissionSetting?->is_result_published);
    }

    public function isPassed(): bool
    {
        return $this->result?->status === ResultStatusEnum::PASSED;
    }
}; ?>

<div>
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Admission</p>
        <h1 class="font-inter font-bold text-2xl text-gray-900">Admission Result</h1>
        <p class="text-gray-400 text-sm mt-1">Your final admission result.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#2F1B72;">
                <x-lucide-award class="size-5" />
            </div>
            <div>
                <h2 class="font-inter font-bold text-lg text-gray-900">Result</h2>
                <p class="text-xs text-gray-500 mt-0.5">Admission result for {{ $applicant->batch?->name ?? '—' }}</p>
            </div>
        </div>

        @if (! $this->isResultPublished())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3">
                <x-lucide-info class="size-5 text-amber-600 mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold text-amber-900">Result is not published yet</p>
                    <p class="text-sm text-amber-700 mt-1">
                        Your result will appear here once it is published by the administration. Please check back later.
                    </p>
                </div>
            </div>
        @elseif (! $result)
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 flex items-start gap-3">
                <x-lucide-circle-help class="size-5 text-gray-500 mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold text-gray-900">No result found</p>
                    <p class="text-sm text-gray-600 mt-1">
                        We could not find a result for your application in this batch. If you believe this is a mistake, please contact the admission office.
                    </p>
                </div>
            </div>
        @else
            @php
                $passed = $this->isPassed();
                $marks = [
                    ['label' => 'MCQ', 'value' => $result->mcq_marks, 'max' => config('result.max_mcq_marks')],
                    ['label' => 'Written', 'value' => $result->written_marks, 'max' => config('result.max_written_marks')],
                    ['label' => 'Viva', 'value' => $result->viva_marks, 'max' => config('result.max_viva_marks')],
                    ['label' => 'Schooling', 'value' => $result->schooling_marks, 'max' => config('result.max_schooling_marks')],
                    ['label' => 'Experience', 'value' => $result->experience_marks, 'max' => config('result.max_experience_marks')],
                ];
            @endphp

            <div class="space-y-5">
                {{-- Outcome hero --}}
                @if ($passed)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 flex items-start gap-3">
                        <x-lucide-party-popper class="size-6 text-emerald-600 mt-0.5 shrink-0" />
                        <div class="flex-1">
                            <p class="font-bold text-emerald-900 text-lg">Congratulations! You have been selected.</p>
                            <p class="text-sm text-emerald-700 mt-1">
                                You have qualified for admission in {{ $applicant->batch?->name ?? 'this batch' }}. The admission office will share the next steps with you.
                            </p>
                            @if ($result->merit_position)
                                <span class="inline-flex items-center gap-1.5 mt-3 px-3 py-1 rounded-full text-sm font-bold text-white" style="background:#2F1B72;">
                                    <x-lucide-medal class="size-4" />
                                    Merit Position: {{ $result->merit_position }}
                                </span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-5 flex items-start gap-3">
                        <x-lucide-circle-alert class="size-6 text-rose-600 mt-0.5 shrink-0" />
                        <div class="flex-1">
                            <p class="font-bold text-rose-900 text-lg">You have not been selected this time.</p>
                            <p class="text-sm text-rose-700 mt-1">
                                We appreciate your interest in the program. We wish you the very best in your future endeavours.
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Summary --}}
                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500">Roll Number</p>
                        <p class="mt-1 font-bold text-gray-900">{{ $application?->roll_number ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500">Total Marks</p>
                        <p class="mt-1 font-bold text-gray-900">
                            {{ number_format((float) $result->total_marks, 2) }}
                            <span class="text-xs font-medium text-gray-400">/ {{ config('result.max_marks') }}</span>
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500">Status</p>
                        <p class="mt-1">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $passed ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                {{ $result->status?->label() ?? '—' }}
                            </span>
                        </p>
                    </div>
                </div>

                {{-- Mark breakdown --}}
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Mark breakdown</p>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                        @foreach ($marks as $mark)
                            <div class="rounded-xl border border-gray-200 p-4 text-center">
                                <p class="text-xs font-medium text-gray-500">{{ $mark['label'] }}</p>
                                <p class="mt-1 font-bold text-gray-900 tabular-nums">
                                    {{ number_format((float) $mark['value'], 2) }}
                                    <span class="text-xs font-medium text-gray-400">/ {{ $mark['max'] }}</span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
