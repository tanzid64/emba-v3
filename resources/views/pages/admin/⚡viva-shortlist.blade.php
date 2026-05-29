<?php

use App\Models\AdmissionResult;
use App\Models\Batch;
use App\Support\CurrentBatch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Viva Shortlist')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'per', except: 20)]
    public int $perPage = 20;

    public ?Batch $batch = null;

    public function mount(): void
    {
        $this->batch = CurrentBatch::get()?->loadMissing('admissionSetting');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    /**
     * MCQ mark at/above which a candidate is eligible to sit for the viva.
     * Per-batch admission setting, falling back to the config default.
     */
    public function vivaThreshold(): float
    {
        return (float) ($this->batch?->admissionSetting?->viva_mcq_threshold
            ?? config('result.viva_mcq_threshold'));
    }

    public function with(): array
    {
        if (! $this->batch) {
            return [
                'results' => null,
                'eligibleCount' => 0,
                'totalCount' => 0,
                'threshold' => $this->vivaThreshold(),
            ];
        }

        $threshold = $this->vivaThreshold();
        $term = trim($this->search);

        $base = AdmissionResult::query()
            ->where('batch_id', $this->batch->id)
            ->where('mcq_marks', '>=', $threshold);

        $totalCount = AdmissionResult::where('batch_id', $this->batch->id)->count();
        $eligibleCount = (clone $base)->count();

        $results = $base
            ->with([
                'applicant.profile:id,applicant_id,full_name',
                'application' => fn ($q) => $q
                    ->where('batch_id', $this->batch->id)
                    ->with('vivaBoard:id,board_name'),
            ])
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('application_number', 'like', $like)
                        ->orWhere('roll_number', 'like', $like)
                        ->orWhereHas('applicant.profile', fn ($p) => $p->where('full_name', 'like', $like));
                });
            })
            ->orderByDesc('mcq_marks')
            ->orderByDesc('total_marks')
            ->paginate($this->perPage);

        return [
            'results' => $results,
            'eligibleCount' => $eligibleCount,
            'totalCount' => $totalCount,
            'threshold' => $threshold,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Viva Shortlist') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">
                @if ($batch)
                    {{ __('Candidates eligible to sit for the viva in') }}
                    <span class="font-semibold text-zinc-700">{{ $batch->name }}</span>
                    <span class="text-zinc-400">·</span>
                    <span class="font-mono text-zinc-600">{{ $batch->code }}</span>
                @else
                    {{ __('Select a batch from the sidebar to view its viva shortlist.') }}
                @endif
            </p>
        </div>
        @if ($batch && $eligibleCount > 0)
            <x-ui.button variant="outline" icon="download"
                x-on:click="$dispatch('open-modal', { name: 'export-viva-shortlist' })">
                {{ __('Export') }}
            </x-ui.button>
        @endif
    </div>

    @if (! $batch)
        <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
            <p class="text-sm text-zinc-500">
                {{ __('No batch selected. Pick one from the sidebar to load its viva shortlist.') }}</p>
        </div>
    @else
        {{-- Summary --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4">
                <p class="text-xs font-medium text-zinc-500">{{ __('Total candidates') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 tabular-nums">{{ number_format($totalCount) }}</p>
                <p class="text-xs text-zinc-500 mt-1">{{ __('with a result row') }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">{{ __('Eligible for viva') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 tabular-nums">{{ number_format($eligibleCount) }}</p>
                <p class="text-xs text-emerald-600/80 mt-1">{{ __('MCQ at or above the cutoff') }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4">
                <p class="text-xs font-medium text-zinc-500">{{ __('MCQ cutoff') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 tabular-nums">
                    {{ rtrim(rtrim(number_format($threshold, 2), '0'), '.') }}
                    <span class="text-base font-medium text-zinc-400">/ {{ config('result.max_mcq_marks') }}</span>
                </p>
                <p class="text-xs text-zinc-500 mt-1">{{ __('set per batch in admission settings') }}</p>
            </div>
        </div>

        <x-ui.table :paginate="$results">
            <x-slot:toolbar>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-[260px] max-w-md">
                        <x-ui.input icon="search" clearable type="search"
                            placeholder="{{ __('Search by name, roll, app. ID…') }}"
                            wire:model.live.debounce.400ms="search" />
                    </div>

                    <select wire:model.live="perPage"
                        class="h-9 rounded-lg border border-zinc-200 bg-white px-3 pe-8 text-sm text-zinc-700 shadow-xs focus:outline-none focus:border-zinc-400">
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}">{{ $size }} / {{ __('page') }}</option>
                        @endforeach
                    </select>

                    <div class="flex items-center gap-2 text-xs text-zinc-400" wire:loading
                        wire:target="search,perPage">
                        <x-lucide-loader-2 class="size-3.5 animate-spin" />
                        {{ __('Loading…') }}
                    </div>
                </div>
            </x-slot:toolbar>

            <x-slot:columns>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3 w-12">{{ __('SL') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3">{{ __('Roll') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3">{{ __('App. ID') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3">{{ __('Name') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3">{{ __('Board') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('MCQ') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('Written') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('Total') }}</th>
            </x-slot:columns>

            @forelse ($results as $result)
                @php $sl = ($results->firstItem() ?? 0) + $loop->index; @endphp
                <tr class="hover:bg-zinc-50/60 transition-colors">
                    <td class="px-3 py-3 text-zinc-500 tabular-nums">{{ $sl }}</td>
                    <td class="px-3 py-3 font-mono font-semibold text-zinc-900 whitespace-nowrap">
                        {{ $result->roll_number }}
                    </td>
                    <td class="px-3 py-3 font-mono text-zinc-700 whitespace-nowrap">
                        {{ $result->application_number }}
                    </td>
                    <td class="px-3 py-3 font-semibold text-zinc-900 uppercase">
                        {{ $result->applicant?->profile?->full_name ?? '—' }}
                    </td>
                    <td class="px-3 py-3 whitespace-nowrap">
                        @if ($result->application?->vivaBoard)
                            <span class="inline-flex items-center rounded-md bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand">
                                {{ $result->application->vivaBoard->board_name }}
                            </span>
                        @else
                            <span class="text-zinc-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-right tabular-nums font-bold text-emerald-700">{{ number_format((float) $result->mcq_marks, 2) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-zinc-700">{{ number_format((float) $result->written_marks, 2) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums font-bold text-zinc-900">{{ number_format((float) $result->total_marks, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-zinc-500">
                        @if ($search !== '')
                            {{ __('No eligible candidates match the current search.') }}
                        @else
                            {{ __('No candidates have reached the MCQ cutoff yet. Upload MCQ marks on the Exam Results page first.') }}
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif

    {{-- ===================== EXPORT MODAL ===================== --}}
    <x-ui.modal name="export-viva-shortlist" :title="__('Export Viva Shortlist')" maxWidth="lg">
        @if ($batch)
            <div class="space-y-4">
                <div class="rounded-lg border border-brand/15 bg-brand-soft px-4 py-3 text-xs text-zinc-700 flex items-start gap-2">
                    <x-lucide-info class="size-4 shrink-0 text-brand mt-0.5" />
                    <p class="leading-relaxed">
                        {{ __('Exports every viva-eligible candidate (MCQ at or above the cutoff) for this batch, highest MCQ first, with a professional header (batch, timestamp, cutoff).') }}
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-2">
                    <x-ui.button variant="outline" icon="file-text" class="flex-1"
                        :href="route('pdf.viva-shortlist', $batch)">
                        {{ __('Download PDF') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" icon="file-spreadsheet" class="flex-1"
                        :href="route('excel.viva-shortlist', $batch)">
                        {{ __('Download Excel') }}
                    </x-ui.button>
                </div>
            </div>

            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
                <x-ui.button variant="ghost"
                    x-on:click="$dispatch('close-modal', { name: 'export-viva-shortlist' })">
                    {{ __('Close') }}
                </x-ui.button>
            </div>
        @endif
    </x-ui.modal>
</div>
