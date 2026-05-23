<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Application Flow')]
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
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Your Progress</p>
        <h1 class="font-inter font-bold text-2xl text-gray-900">Application Flow</h1>
        <p class="text-gray-400 text-sm mt-1">Track each step of your admission application.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 max-w-2xl">
        <ol class="relative">
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
</div>
