<?php

use App\Enums\PaymentStatusEnum;
use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Payments')]
#[Layout('layouts.applicant.app')]
class extends Component {
    use WithPagination;

    #[Url(as: 'status')]
    public string $status = '';

    public int $perPage = 10;

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function paymentStatuses(): array
    {
        return PaymentStatusEnum::cases();
    }

    public function paymentBadgeColor(?PaymentStatusEnum $status): string
    {
        return match ($status) {
            PaymentStatusEnum::PAID, PaymentStatusEnum::COMPLETED => 'green',
            PaymentStatusEnum::PENDING => 'yellow',
            PaymentStatusEnum::FAILED => 'red',
            PaymentStatusEnum::UNPAID => 'zinc',
            default => 'zinc',
        };
    }

    public function with(): array
    {
        $payments = Payment::query()
            ->where('applicant_id', auth('applicant')->id())
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->with('batch')
            ->latest()
            ->paginate($this->perPage);

        return [
            'payments' => $payments,
        ];
    }
}; ?>

<div>
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Applicant Portal</p>
        <h1 class="font-inter font-bold text-2xl text-gray-900">Payments</h1>
        <p class="text-gray-400 text-sm mt-1">All payments you have made for your application.</p>
    </div>

    <x-ui.table :paginate="$payments">
        <x-slot:toolbar>
            <div class="flex items-center gap-3 flex-wrap">
                <select
                    wire:model.live="status"
                    class="h-9 rounded-lg border border-zinc-200 bg-white px-3 pe-8 text-sm text-zinc-700 shadow-xs focus:outline-none focus:border-zinc-400"
                >
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($this->paymentStatuses() as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </select>

                <select
                    wire:model.live="perPage"
                    class="h-9 rounded-lg border border-zinc-200 bg-white px-3 pe-8 text-sm text-zinc-700 shadow-xs focus:outline-none focus:border-zinc-400"
                >
                    @foreach ([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}">{{ $size }} / {{ __('page') }}</option>
                    @endforeach
                </select>

                <div class="flex items-center gap-2 text-xs text-zinc-400" wire:loading wire:target="status,perPage">
                    <x-lucide-loader-2 class="size-3.5 animate-spin" />
                    {{ __('Loading…') }}
                </div>
            </div>
        </x-slot:toolbar>

        <x-slot:columns>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3 w-12">{{ __('SL') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Payment No.') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Batch') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Method') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Trx ID') }}</th>
            <th class="text-right font-semibold text-zinc-700 px-4 py-3">{{ __('Amount') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Status') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Paid At') }}</th>
            <th class="text-right font-semibold text-zinc-700 px-4 py-3 w-24">{{ __('Action') }}</th>
        </x-slot:columns>

        @forelse ($payments as $payment)
            @php $sl = ($payments->firstItem() ?? 0) + $loop->index; @endphp
            <tr class="hover:bg-zinc-50/60 transition-colors align-top">
                <td class="px-4 py-3 text-zinc-500 tabular-nums">{{ $sl }}</td>

                <td class="px-4 py-3 font-mono text-zinc-800 whitespace-nowrap">
                    {{ $payment->payment_number }}
                </td>

                <td class="px-4 py-3 text-sm text-zinc-700 whitespace-nowrap">
                    {{ $payment->batch?->name ?? '—' }}
                </td>

                <td class="px-4 py-3 text-sm text-zinc-700 whitespace-nowrap">
                    {{ $payment->payment_method?->label() ?? '—' }}
                </td>

                <td class="px-4 py-3 text-sm text-zinc-700 font-mono">
                    {{ $payment->gateway_trx_id ?? '—' }}
                </td>

                <td class="px-4 py-3 text-sm text-zinc-900 font-semibold text-right whitespace-nowrap tabular-nums">
                    ৳ {{ number_format((float) $payment->amount, 2) }}
                </td>

                <td class="px-4 py-3">
                    <x-ui.badge :color="$this->paymentBadgeColor($payment->status)" class="w-fit">
                        {{ $payment->status?->label() ?? '—' }}
                    </x-ui.badge>
                </td>

                <td class="px-4 py-3 text-sm text-zinc-700 whitespace-nowrap">
                    {{ $payment->paid_at['formatted'] ?? '—' }}
                </td>

                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-1.5">
                        @if ($payment->status === PaymentStatusEnum::COMPLETED)
                            <x-ui.tooltip text="{{ __('View receipt') }}">
                                <a
                                    href="{{ route('pdf.payment-receipt', $payment->payment_number) }}"
                                    target="_blank"
                                    rel="noopener"
                                    aria-label="{{ __('View receipt') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors"
                                >
                                    <x-lucide-eye class="size-4" />
                                </a>
                            </x-ui.tooltip>

                            <x-ui.tooltip text="{{ __('Download receipt') }}">
                                <a
                                    href="{{ route('pdf.payment-receipt', ['paymentNo' => $payment->payment_number, 'action' => 'download']) }}"
                                    aria-label="{{ __('Download receipt') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors"
                                >
                                    <x-lucide-download class="size-4" />
                                </a>
                            </x-ui.tooltip>
                        @else
                            <span class="text-xs text-zinc-400">—</span>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-4 py-10 text-center text-zinc-500">
                    @if ($status !== '')
                        {{ __('No payments match the current filter.') }}
                    @else
                        {{ __('You have not made any payments yet.') }}
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</div>
