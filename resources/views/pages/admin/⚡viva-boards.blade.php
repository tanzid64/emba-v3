<?php

use App\Models\Batch;
use App\Models\VivaBoard;
use App\Services\VivaBoardAssignmentService;
use App\Support\CurrentBatch;
use App\Support\Toast;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Viva Board')] #[Layout('layouts.app')] class extends Component {
    public ?Batch $batch = null;

    // null = creating a new board; an id = editing that board.
    public ?int $editingId = null;

    public string $boardName = '';

    public string $centerNo = '';

    public string $centerName = '';

    public string $roomName = '';

    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->batch = CurrentBatch::get();
    }

    /**
     * Spread the batch's viva-eligible candidates across its boards,
     * leaving anyone already assigned in place (see
     * VivaBoardAssignmentService).
     */
    public function assignBoards(VivaBoardAssignmentService $service): void
    {
        if (! $this->batch) {
            return;
        }

        if (VivaBoard::where('batch_id', $this->batch->id)->doesntExist()) {
            Toast::warning(__('Add at least one board before assigning candidates.'));

            return;
        }

        $assigned = $service->assignUnassigned($this->batch);

        if ($assigned === 0) {
            Toast::info(__('No new candidates to assign — every eligible candidate already has a board.'));

            return;
        }

        Toast::success(__(':count candidate(s) assigned across the boards.', [
            'count' => number_format($assigned),
        ]));
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->dispatch('open-modal', name: 'viva-board-form');
    }

    public function openEditModal(int $id): void
    {
        $board = VivaBoard::where('batch_id', $this->batch?->id)->findOrFail($id);

        $this->resetErrorBag();
        $this->editingId = $board->id;
        $this->boardName = $board->board_name;
        $this->centerNo = (string) $board->center_no;
        $this->centerName = (string) $board->center_name;
        $this->roomName = (string) $board->room_name;
        $this->dispatch('open-modal', name: 'viva-board-form');
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'viva-board-form');
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->boardName = '';
        $this->centerNo = '';
        $this->centerName = '';
        $this->roomName = '';
    }

    public function save(): void
    {
        if (! $this->batch) {
            return;
        }

        $this->validate([
            'boardName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('viva_boards', 'board_name')
                    ->where('batch_id', $this->batch->id)
                    ->ignore($this->editingId),
            ],
            'centerNo' => ['nullable', 'string', 'max:255'],
            'centerName' => ['nullable', 'string', 'max:255'],
            'roomName' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'boardName' => __('board name'),
            'centerNo' => __('center no'),
            'centerName' => __('center name'),
            'roomName' => __('room name'),
        ]);

        $payload = [
            'board_name' => trim($this->boardName),
            'center_no' => $this->centerNo === '' ? null : trim($this->centerNo),
            'center_name' => $this->centerName === '' ? null : trim($this->centerName),
            'room_name' => $this->roomName === '' ? null : trim($this->roomName),
        ];

        if ($this->editingId) {
            VivaBoard::where('batch_id', $this->batch->id)
                ->whereKey($this->editingId)
                ->update($payload);

            Toast::success(__('Board updated.'));
        } else {
            VivaBoard::create(['batch_id' => $this->batch->id, ...$payload]);

            Toast::success(__('Board added.'));
        }

        $this->closeFormModal();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->dispatch('open-modal', name: 'viva-board-delete');
    }

    public function delete(): void
    {
        if (! $this->batch || ! $this->deletingId) {
            return;
        }

        // nullOnDelete on applications.viva_board_id un-assigns any candidates.
        VivaBoard::where('batch_id', $this->batch->id)
            ->whereKey($this->deletingId)
            ->delete();

        $this->deletingId = null;
        $this->dispatch('close-modal', name: 'viva-board-delete');

        Toast::success(__('Board deleted.'));
    }

    public function with(): array
    {
        if (! $this->batch) {
            return ['boards' => collect()];
        }

        return [
            'boards' => VivaBoard::where('batch_id', $this->batch->id)
                ->withCount('applications as assigned_count')
                ->orderBy('board_name')
                ->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Viva Board') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">
                @if ($batch)
                    {{ __('Examination boards for') }}
                    <span class="font-semibold text-zinc-700">{{ $batch->name }}</span>
                    <span class="text-zinc-400">·</span>
                    <span class="font-mono text-zinc-600">{{ $batch->code }}</span>
                @else
                    {{ __('Select a batch from the sidebar to manage its viva boards.') }}
                @endif
            </p>
        </div>
        @if ($batch)
            <div class="flex items-center gap-2">
                @if ($boards->isNotEmpty())
                    <x-ui.button variant="outline" wire:click="assignBoards"
                        wire:loading.attr="disabled" wire:target="assignBoards">
                        <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="assignBoards" />
                        <x-lucide-clipboard-check wire:loading.remove wire:target="assignBoards" />
                        <span wire:loading.remove wire:target="assignBoards">{{ __('Assign Board') }}</span>
                        <span wire:loading wire:target="assignBoards">{{ __('Assigning…') }}</span>
                    </x-ui.button>
                @endif
                <x-ui.button variant="primary" icon="plus" wire:click="openCreateModal">
                    {{ __('Add Board') }}
                </x-ui.button>
            </div>
        @endif
    </div>

    @if (! $batch)
        <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
            <p class="text-sm text-zinc-500">
                {{ __('No batch selected. Pick one from the sidebar to manage its viva boards.') }}</p>
        </div>
    @else
        <x-ui.table>
            <x-slot:columns>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3 w-12">{{ __('SL') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Board Name') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Center') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Room') }}</th>
                <th class="text-center font-semibold text-zinc-700 px-4 py-3 w-36">{{ __('Assigned') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-4 py-3 w-28">{{ __('Action') }}</th>
            </x-slot:columns>

            @forelse ($boards as $board)
                <tr class="hover:bg-zinc-50/60 transition-colors">
                    <td class="px-4 py-3 text-zinc-500 tabular-nums">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3 font-semibold text-zinc-900">{{ $board->board_name }}</td>
                    <td class="px-4 py-3 text-zinc-700">
                        @if ($board->center_name || $board->center_no)
                            {{ $board->center_name ?? '—' }}
                            @if ($board->center_no)
                                <span class="text-zinc-400 text-xs">({{ $board->center_no }})</span>
                            @endif
                        @else
                            <span class="text-zinc-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-zinc-700">{{ $board->room_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-center tabular-nums text-zinc-700">
                        {{ number_format($board->assigned_count) }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-ui.tooltip text="{{ __('Edit board') }}">
                                <button type="button" wire:click="openEditModal({{ $board->id }})"
                                    aria-label="{{ __('Edit board') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors">
                                    <x-lucide-pencil class="size-4" />
                                </button>
                            </x-ui.tooltip>
                            <x-ui.tooltip text="{{ __('Delete board') }}">
                                <button type="button" wire:click="confirmDelete({{ $board->id }})"
                                    aria-label="{{ __('Delete board') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-red-300 hover:text-red-600 transition-colors">
                                    <x-lucide-trash-2 class="size-4" />
                                </button>
                            </x-ui.tooltip>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-zinc-500">
                        {{ __('No viva boards yet for this batch. Click "Add Board" to create one.') }}
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif

    {{-- ===================== CREATE / EDIT MODAL ===================== --}}
    <x-ui.modal name="viva-board-form" :title="$editingId ? __('Edit Board') : __('Add Board')" maxWidth="md">
        <div class="space-y-3">
            <div>
                <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Board name') }} <span class="text-red-500">*</span></label>
                <x-ui.input type="text" wire:model="boardName" placeholder="{{ __('e.g. Board A') }}" autofocus />
                @error('boardName')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Center no') }}</label>
                    <x-ui.input type="text" wire:model="centerNo" placeholder="{{ __('e.g. C-01') }}" />
                    @error('centerNo')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Room') }}</label>
                    <x-ui.input type="text" wire:model="roomName" wire:keydown.enter="save"
                        placeholder="{{ __('e.g. Room 101') }}" />
                    @error('roomName')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Center name') }}</label>
                <x-ui.input type="text" wire:model="centerName" placeholder="{{ __('e.g. Main Campus — Building A') }}" />
                @error('centerName')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
            <x-ui.button variant="ghost" wire:click="closeFormModal">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="save" />
                <x-lucide-check wire:loading.remove wire:target="save" />
                <span wire:loading.remove wire:target="save">{{ $editingId ? __('Save changes') : __('Add board') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </x-ui.button>
        </div>
    </x-ui.modal>

    {{-- ===================== DELETE CONFIRM MODAL ===================== --}}
    <x-ui.modal name="viva-board-delete" :title="__('Delete Board')" maxWidth="md">
        <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <x-lucide-alert-triangle class="size-5 shrink-0 text-amber-600 mt-0.5" />
            <p class="text-sm text-zinc-700 leading-relaxed">
                {{ __('This permanently removes the board. Any candidates assigned to it will be un-assigned (their other data is untouched).') }}
            </p>
        </div>

        <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
            <x-ui.button variant="ghost"
                x-on:click="$dispatch('close-modal', { name: 'viva-board-delete' })">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="delete" />
                <x-lucide-trash-2 wire:loading.remove wire:target="delete" />
                <span wire:loading.remove wire:target="delete">{{ __('Delete board') }}</span>
                <span wire:loading wire:target="delete">{{ __('Deleting…') }}</span>
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>
