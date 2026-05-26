<?php

use App\Enums\ResultStatusEnum;
use App\Http\Controllers\ExcelExportController;
use App\Http\Controllers\PDFController;
use App\Models\AdmissionResult;
use App\Models\Batch;
use App\Services\ResultGenerationService;
use App\Support\CurrentBatch;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Exam Results')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'per', except: 20)]
    public int $perPage = 20;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    public ?Batch $batch = null;

    // Export filters — wired up in the export modal.
    public string $exportStatusFilter = '';

    public ?int $exportMeritFrom = null;

    public ?int $exportMeritTo = null;

    public function mount(): void
    {
        $this->batch = CurrentBatch::get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function openGenerateMeritModal(): void
    {
        $this->dispatch('open-modal', name: 'generate-merit-list');
    }

    public function closeGenerateMeritModal(): void
    {
        $this->dispatch('close-modal', name: 'generate-merit-list');
    }

    public function generateMeritList(ResultGenerationService $service): void
    {
        if (! $this->batch) {
            return;
        }

        $ranked = $service->generateMeritList($this->batch);

        $this->dispatch('close-modal', name: 'generate-merit-list');
        $this->resetPage();

        Toast::success(__('Merit list generated — :n candidate(s) ranked.', ['n' => number_format($ranked)]));
    }

    public function openExportModal(): void
    {
        $this->resetErrorBag();
        $this->exportStatusFilter = '';
        $this->exportMeritFrom = null;
        $this->exportMeritTo = null;
        $this->dispatch('open-modal', name: 'export-exam-results');
    }

    public function closeExportModal(): void
    {
        $this->dispatch('close-modal', name: 'export-exam-results');
    }

    private const EXPORT_RULES = [
        'exportMeritFrom' => ['nullable', 'integer', 'min:1'],
        'exportMeritTo' => ['nullable', 'integer', 'min:1', 'gte:exportMeritFrom'],
    ];

    private const EXPORT_ATTRS = [
        'exportMeritFrom' => 'merit from',
        'exportMeritTo' => 'merit to',
    ];

    /**
     * Build the synthetic request the export controllers expect. Reuses
     * the live HTTP request so the auth user (admin) carries through to
     * the controllers' ensureAdmin() guard.
     */
    private function exportRequest(): \Illuminate\Http\Request
    {
        $request = request();
        $request->query->replace([
            'status' => $this->exportStatusFilter !== '' ? $this->exportStatusFilter : null,
            'merit_from' => $this->exportMeritFrom,
            'merit_to' => $this->exportMeritTo,
        ]);

        return $request;
    }

    public function exportExcel(ExcelExportController $controller)
    {
        if (! $this->batch) {
            return null;
        }

        $this->validate(self::EXPORT_RULES, attributes: self::EXPORT_ATTRS);
        $this->dispatch('close-modal', name: 'export-exam-results');

        return $controller->examResults($this->exportRequest(), $this->batch);
    }

    public function exportPdf(PDFController $controller)
    {
        if (! $this->batch) {
            return null;
        }

        $this->validate(self::EXPORT_RULES, attributes: self::EXPORT_ATTRS);
        $this->dispatch('close-modal', name: 'export-exam-results');

        return $controller->generateExamResultsPDF($this->exportRequest(), $this->batch);
    }

    public function with(): array
    {
        if (! $this->batch) {
            return [
                'results' => null,
                'totalCount' => 0,
                'passedCount' => 0,
                'failedCount' => 0,
            ];
        }

        $term = trim($this->search);

        $base = AdmissionResult::query()->where('batch_id', $this->batch->id);
        $totalCount = (clone $base)->count();
        $passedCount = (clone $base)->where('status', ResultStatusEnum::PASSED->value)->count();
        $failedCount = $totalCount - $passedCount;

        $results = $base
            ->with(['applicant.profile:id,applicant_id,full_name'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('application_number', 'like', $like)
                        ->orWhere('roll_number', 'like', $like)
                        ->orWhereHas('applicant.profile', fn ($p) => $p->where('full_name', 'like', $like));
                });
            })
            // Passed candidates with a merit_position float to the top in rank order;
            // ungeneraged / failed rows fall through to total_marks ordering below.
            ->orderByRaw('merit_position IS NULL')
            ->orderBy('merit_position')
            ->orderByDesc('total_marks')
            ->paginate($this->perPage);

        return [
            'results' => $results,
            'totalCount' => $totalCount,
            'passedCount' => $passedCount,
            'failedCount' => $failedCount,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Exam Results') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">
                @if ($batch)
                    {{ __('Mark sheet and merit list for') }}
                    <span class="font-semibold text-zinc-700">{{ $batch->name }}</span>
                    <span class="text-zinc-400">·</span>
                    <span class="font-mono text-zinc-600">{{ $batch->code }}</span>
                @else
                    {{ __('Select a batch from the sidebar to view its exam results.') }}
                @endif
            </p>
        </div>
        @if ($batch && $totalCount > 0)
            <div class="flex items-center gap-2 flex-wrap">
                <x-ui.button variant="outline" icon="download" wire:click="openExportModal">
                    {{ __('Export') }}
                </x-ui.button>
                <x-ui.button variant="primary" icon="trophy" wire:click="openGenerateMeritModal">
                    {{ __('Generate Merit List') }}
                </x-ui.button>
            </div>
        @endif
    </div>

    @if (! $batch)
        <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
            <p class="text-sm text-zinc-500">
                {{ __('No batch selected. Pick one from the sidebar to load its exam results.') }}</p>
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
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">{{ __('Passed') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 tabular-nums">{{ number_format($passedCount) }}</p>
                <p class="text-xs text-emerald-600/80 mt-1">{{ __('above passing marks') }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">{{ __('Failed') }}</p>
                <p class="mt-1 text-2xl font-bold text-rose-700 tabular-nums">{{ number_format($failedCount) }}</p>
                <p class="text-xs text-rose-600/80 mt-1">{{ __('below cutoff or unranked') }}</p>
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

                    <select wire:model.live="statusFilter"
                        class="h-9 rounded-lg border border-zinc-200 bg-white px-3 pe-8 text-sm text-zinc-700 shadow-xs focus:outline-none focus:border-zinc-400">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="{{ ResultStatusEnum::PASSED->value }}">{{ __('Passed only') }}</option>
                        <option value="{{ ResultStatusEnum::FAILED->value }}">{{ __('Failed only') }}</option>
                    </select>

                    <select wire:model.live="perPage"
                        class="h-9 rounded-lg border border-zinc-200 bg-white px-3 pe-8 text-sm text-zinc-700 shadow-xs focus:outline-none focus:border-zinc-400">
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}">{{ $size }} / {{ __('page') }}</option>
                        @endforeach
                    </select>

                    <div class="flex items-center gap-2 text-xs text-zinc-400" wire:loading
                        wire:target="search,perPage,statusFilter">
                        <x-lucide-loader-2 class="size-3.5 animate-spin" />
                        {{ __('Loading…') }}
                    </div>
                </div>
            </x-slot:toolbar>

            <x-slot:columns>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3 w-12">{{ __('SL') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3 w-16">{{ __('Merit') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3">{{ __('Roll') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3">{{ __('App. ID') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-3 py-3">{{ __('Name') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('MCQ') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('Written') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('Viva') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('Schl.') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('Exp.') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-3 py-3">{{ __('Total') }}</th>
                <th class="text-center font-semibold text-zinc-700 px-3 py-3 w-24">{{ __('Status') }}</th>
            </x-slot:columns>

            @forelse ($results as $result)
                @php
                    $sl = ($results->firstItem() ?? 0) + $loop->index;
                    $isPassed = $result->status === ResultStatusEnum::PASSED;
                @endphp
                <tr class="hover:bg-zinc-50/60 transition-colors">
                    <td class="px-3 py-3 text-zinc-500 tabular-nums">{{ $sl }}</td>
                    <td class="px-3 py-3 font-bold text-zinc-900 tabular-nums">
                        @if ($result->merit_position)
                            {{ $result->merit_position }}
                        @else
                            <span class="text-zinc-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-3 font-mono font-semibold text-zinc-900 whitespace-nowrap">
                        {{ $result->roll_number }}
                    </td>
                    <td class="px-3 py-3 font-mono text-zinc-700 whitespace-nowrap">
                        {{ $result->application_number }}
                    </td>
                    <td class="px-3 py-3 font-semibold text-zinc-900 uppercase">
                        {{ $result->applicant?->profile?->full_name ?? '—' }}
                    </td>
                    <td class="px-3 py-3 text-right tabular-nums text-zinc-700">{{ number_format((float) $result->mcq_marks, 2) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-zinc-700">{{ number_format((float) $result->written_marks, 2) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-zinc-700">{{ number_format((float) $result->viva_marks, 2) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-zinc-700">{{ number_format((float) $result->schooling_marks, 2) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-zinc-700">{{ number_format((float) $result->experience_marks, 2) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums font-bold text-zinc-900">{{ number_format((float) $result->total_marks, 2) }}</td>
                    <td class="px-3 py-3 text-center">
                        <x-ui.badge size="sm" :color="$isPassed ? 'green' : 'red'">
                            {{ $result->status?->label() ?? '—' }}
                        </x-ui.badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="px-4 py-10 text-center text-zinc-500">
                        @if ($search !== '' || $statusFilter !== '')
                            {{ __('No results match the current filters.') }}
                        @else
                            {{ __('No exam results yet for this batch.') }}
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif

    {{-- ===================== GENERATE MERIT MODAL ===================== --}}
    <x-ui.modal name="generate-merit-list" :title="__('Generate Merit List')" maxWidth="md">
        @if ($batch)
            <div class="space-y-4">
                <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <x-lucide-alert-triangle class="size-5 shrink-0 text-amber-600 mt-0.5" />
                    <div class="text-sm text-zinc-700 leading-relaxed">
                        <p class="font-semibold text-amber-800">{{ __('This will recompute every candidate\'s rank.') }}</p>
                        <ul class="list-disc list-inside space-y-1 mt-1">
                            <li>{{ __('Pass / Fail status is set from current total marks against the configured cutoff.') }}</li>
                            <li>{{ __('Only PASSED candidates receive a merit position; FAILED rows are cleared to null.') }}</li>
                            <li>{{ __('Ties are broken by MCQ → Experience → Written → Schooling → Viva, in that order.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
                <x-ui.button variant="ghost" wire:click="closeGenerateMeritModal">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="generateMeritList" wire:loading.attr="disabled"
                    wire:target="generateMeritList">
                    <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="generateMeritList" />
                    <x-lucide-trophy wire:loading.remove wire:target="generateMeritList" />
                    <span wire:loading.remove wire:target="generateMeritList">{{ __('Generate') }}</span>
                    <span wire:loading wire:target="generateMeritList">{{ __('Ranking…') }}</span>
                </x-ui.button>
            </div>
        @endif
    </x-ui.modal>

    {{-- ===================== EXPORT MODAL (Excel / PDF) ===================== --}}
    <x-ui.modal name="export-exam-results" :title="__('Export Exam Results')" maxWidth="lg">
        @if ($batch)
            @php
                $inputClasses = 'block w-full rounded-lg border border-zinc-200 bg-white text-sm text-zinc-800 shadow-xs px-3 py-1.5 placeholder-zinc-400 focus:outline-none focus:border-brand';
            @endphp

            <div class="space-y-4">
                <div class="rounded-lg border border-brand/15 bg-brand-soft px-4 py-3 text-xs text-zinc-700 flex items-start gap-2">
                    <x-lucide-info class="size-4 shrink-0 text-brand mt-0.5" />
                    <p class="leading-relaxed">
                        {{ __('Filter the rows you want to include. Leave a field blank to skip that filter. The exported workbook includes a professional header (batch, generation timestamp, totals).') }}
                    </p>
                </div>

                <div>
                    <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Status') }}</label>
                    <select wire:model="exportStatusFilter" class="{{ $inputClasses }}">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="{{ ResultStatusEnum::PASSED->value }}">{{ __('Passed only') }}</option>
                        <option value="{{ ResultStatusEnum::FAILED->value }}">{{ __('Failed only') }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Merit position from') }}</label>
                        <input type="number" min="1" wire:model="exportMeritFrom"
                            placeholder="{{ __('e.g. 1') }}" class="{{ $inputClasses }}" />
                        @error('exportMeritFrom')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Merit position to') }}</label>
                        <input type="number" min="1" wire:model="exportMeritTo"
                            placeholder="{{ __('e.g. 100') }}" class="{{ $inputClasses }}" />
                        @error('exportMeritTo')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <p class="text-xs text-zinc-500">
                    {{ __('Merit-range filters only include candidates with a merit_position — run "Generate Merit List" first if positions are still empty.') }}
                </p>
            </div>

            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100 flex-wrap">
                <x-ui.button variant="ghost" wire:click="closeExportModal">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button variant="outline" wire:click="exportPdf" wire:loading.attr="disabled"
                    wire:target="exportPdf,exportExcel">
                    <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="exportPdf" />
                    <x-lucide-file-text wire:loading.remove wire:target="exportPdf" />
                    <span wire:loading.remove wire:target="exportPdf">{{ __('Download PDF') }}</span>
                    <span wire:loading wire:target="exportPdf">{{ __('Building…') }}</span>
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="exportExcel" wire:loading.attr="disabled"
                    wire:target="exportExcel,exportPdf">
                    <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="exportExcel" />
                    <x-lucide-file-spreadsheet wire:loading.remove wire:target="exportExcel" />
                    <span wire:loading.remove wire:target="exportExcel">{{ __('Download Excel') }}</span>
                    <span wire:loading wire:target="exportExcel">{{ __('Building…') }}</span>
                </x-ui.button>
            </div>
        @endif
    </x-ui.modal>
</div>
