<?php

use App\Concerns\PasswordValidationRules;
use App\Enum\BatchStatusEnum;
use App\Models\Batch;
use App\Support\Toast;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admission Batch')]
#[Layout('layouts.app')]
class extends Component {
    use PasswordValidationRules;

    public Collection $batches;

    public ?int $deletingBatchId = null;

    public string $deletingBatchName = '';

    public string $confirmName = '';

    public string $password = '';

    public function mount(): void
    {
        $this->loadBatches();
    }

    public function confirmDelete(int $batchId): void
    {
        $batch = Batch::findOrFail($batchId);

        if ($batch->status === BatchStatusEnum::OPEN) {
            Toast::error(__('An open batch cannot be deleted. Close it first.'));

            return;
        }

        $this->deletingBatchId = $batch->id;
        $this->deletingBatchName = $batch->name;
        $this->reset('confirmName', 'password');
        $this->resetErrorBag();

        $this->dispatch('open-modal', name: 'delete-batch');
    }

    public function delete(): void
    {
        $this->validate([
            'confirmName' => ['required', 'string'],
            'password' => $this->currentPasswordRules(),
        ]);

        $batch = Batch::findOrFail($this->deletingBatchId);

        if ($batch->status === BatchStatusEnum::OPEN) {
            Toast::error(__('An open batch cannot be deleted. Close it first.'));

            return;
        }

        if (trim($this->confirmName) !== $batch->name) {
            $this->addError('confirmName', __('The name you entered does not match the batch name.'));

            return;
        }

        $batch->delete();

        $deletedName = $batch->name;

        $this->reset('deletingBatchId', 'deletingBatchName', 'confirmName', 'password');
        $this->loadBatches();

        $this->dispatch('close-modal', name: 'delete-batch');
        Toast::success(__('Batch ":name" and all of its data were permanently deleted.', ['name' => $deletedName]));
    }

    protected function loadBatches(): void
    {
        $this->batches = Batch::query()
            ->withCount(['applicants', 'applications'])
            ->orderByDesc('admission_year')
            ->orderByDesc('id')
            ->get();
    }

    public function statusColor(?BatchStatusEnum $status): string
    {
        return match ($status) {
            BatchStatusEnum::OPEN => 'green',
            BatchStatusEnum::CLOSED => 'red',
            BatchStatusEnum::DRAFT => 'yellow',
            default => 'zinc',
        };
    }

    public function statusLabel(?BatchStatusEnum $status): string
    {
        return $status ? ucfirst($status->value) : '—';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Admission Batch') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">{{ __('All admission cycles managed in the system.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <x-ui.badge size="sm" color="zinc">
                {{ trans_choice(':count batch|:count batches', $batches->count(), ['count' => $batches->count()]) }}
            </x-ui.badge>
            <x-ui.button variant="primary" icon="plus" :href="route('admin.batches.create')" wire:navigate>
                {{ __('Add new batch') }}
            </x-ui.button>
        </div>
    </div>

    <x-ui.table>
        <x-slot:columns>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Batch') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Code') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Admission Year') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Status') }}</th>
            <th class="text-right font-semibold text-zinc-700 px-4 py-3">{{ __('Applicants') }}</th>
            <th class="text-right font-semibold text-zinc-700 px-4 py-3">{{ __('Applications') }}</th>
            <th class="text-right font-semibold text-zinc-700 px-4 py-3">{{ __('Actions') }}</th>
        </x-slot:columns>

        @forelse ($batches as $batch)
            <tr class="hover:bg-zinc-50/60 transition-colors">
                <td class="px-4 py-3 font-medium text-zinc-900">{{ $batch->name }}</td>
                <td class="px-4 py-3 font-mono text-zinc-700">{{ $batch->code }}</td>
                <td class="px-4 py-3 text-zinc-700">{{ $batch->admission_year }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :color="$this->statusColor($batch->status)" size="sm">
                        {{ $this->statusLabel($batch->status) }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 text-right text-zinc-700 tabular-nums">{{ number_format($batch->applicants_count) }}</td>
                <td class="px-4 py-3 text-right text-zinc-700 tabular-nums">{{ number_format($batch->applications_count) }}</td>
                <td class="px-4 py-3 text-right">
                    @unless ($batch->status === \App\Enum\BatchStatusEnum::OPEN)
                        <x-ui.button variant="danger" size="sm" icon="trash"
                            wire:click="confirmDelete({{ $batch->id }})" :aria-label="__('Delete batch')">
                            {{ __('Delete') }}
                        </x-ui.button>
                    @else
                        <x-ui.tooltip text="{{ __('Close the batch before it can be deleted.') }}">
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-zinc-400">
                                <x-lucide-lock class="size-3.5" />
                                {{ __('Locked') }}
                            </span>
                        </x-ui.tooltip>
                    @endunless
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-10 text-center text-zinc-500">
                    {{ __('No batches have been created yet.') }}
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <x-ui.modal name="delete-batch" :title="__('Delete Batch')" maxWidth="lg">
        <div class="space-y-5">
            <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <x-lucide-alert-triangle class="size-5 shrink-0 text-red-600 mt-0.5" />
                <div class="space-y-2 text-sm text-zinc-700 leading-relaxed">
                    <p class="font-semibold text-red-700">
                        {{ __('Deleting ":name" cannot be undone.', ['name' => $deletingBatchName]) }}
                    </p>
                    <p>{{ __('This will permanently erase the batch and every record tied to it, including:') }}</p>
                    <ul class="list-disc space-y-0.5 pl-5">
                        <li>{{ __('All applicants and their profiles') }}</li>
                        <li>{{ __('All applications submitted to this batch') }}</li>
                        <li>{{ __('All payment records') }}</li>
                        <li>{{ __('All admission results, exam centers and viva boards') }}</li>
                        <li>{{ __('The admission settings for this batch') }}</li>
                    </ul>
                    <p class="font-medium">{{ __('No backup is kept. Once deleted, this data is gone for good.') }}</p>
                </div>
            </div>

            <div>
                <label class="block mb-1.5 text-xs font-semibold text-zinc-700">
                    {{ __('Type the batch name to confirm') }} <span class="text-red-500">*</span>
                </label>
                <div class="mb-2 flex items-center gap-2">
                    <code class="rounded bg-zinc-100 px-2 py-1 font-mono text-sm text-zinc-900">{{ $deletingBatchName }}</code>
                    <button type="button" x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($deletingBatchName)); copied = true; setTimeout(() => copied = false, 1500)"
                        class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs font-medium text-zinc-600 transition-colors hover:border-brand/40 hover:text-brand">
                        <x-lucide-copy class="size-3.5" x-show="!copied" />
                        <x-lucide-check class="size-3.5 text-green-600" x-show="copied" x-cloak />
                        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))"></span>
                    </button>
                </div>
                <x-ui.input type="text" wire:model="confirmName" autocomplete="off"
                    placeholder="{{ $deletingBatchName }}" />
                @error('confirmName')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block mb-1.5 text-xs font-semibold text-zinc-700">
                    {{ __('Your account password') }} <span class="text-red-500">*</span>
                </label>
                <x-ui.input type="password" wire:model="password" autocomplete="current-password"
                    wire:keydown.enter="delete" placeholder="{{ __('Enter your password') }}" />
                @error('password')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', { name: 'delete-batch' })">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="delete" />
                <x-lucide-trash-2 wire:loading.remove wire:target="delete" />
                <span wire:loading.remove wire:target="delete">{{ __('Permanently delete batch') }}</span>
                <span wire:loading wire:target="delete">{{ __('Deleting…') }}</span>
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>
