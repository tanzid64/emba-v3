<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Payment — {{ $payment->payment_number }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-100 antialiased text-zinc-800 font-sans">

@php
    $applicant = $payment->applicant;
    $profile = $applicant?->profile;
    $batch = $payment->batch ?? $applicant?->batch;
    $paidAt = $payment->getRawOriginal('paid_at')
        ? \Carbon\Carbon::parse($payment->getRawOriginal('paid_at'))->format('d M Y, h:i A')
        : null;
    $completed = $payment->status === \App\Enums\PaymentStatusEnum::COMPLETED;
@endphp

<div class="mx-auto max-w-2xl px-4 py-10 sm:py-16">
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <img src="{{ asset('assets/logo/logo.jpg') }}" alt="University of Dhaka" class="size-12 rounded">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-brand">Payment Verification</p>
            <p class="text-sm font-semibold text-zinc-700">Executive MBA, Faculty of Business Studies — University of Dhaka</p>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden">
        {{-- Status banner --}}
        @if ($completed)
            <div class="flex items-center gap-3 px-6 py-4 bg-emerald-50 border-b border-emerald-200">
                <span class="inline-flex items-center justify-center size-10 rounded-full bg-emerald-500 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-base font-bold text-emerald-900">Authentic payment</h1>
                    <p class="text-sm text-emerald-800">This payment is on record with the EMBA admission office.</p>
                </div>
            </div>
        @else
            <div class="flex items-center gap-3 px-6 py-4 bg-amber-50 border-b border-amber-200">
                <span class="inline-flex items-center justify-center size-10 rounded-full bg-amber-500 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                        <path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-base font-bold text-amber-900">Payment not completed</h1>
                    <p class="text-sm text-amber-800">This payment record exists but is not in a completed state.</p>
                </div>
            </div>
        @endif

        {{-- Summary --}}
        <div class="px-6 py-6">
            <div class="text-center mb-6 pb-6 border-b border-zinc-100">
                <p class="text-xs font-bold uppercase tracking-widest text-zinc-500">Amount Paid</p>
                <p class="font-mono font-bold text-3xl text-brand mt-1">৳ {{ number_format((float) $payment->amount) }}</p>
                <p class="text-xs text-zinc-500 mt-1">{{ $payment->actor_table?->label() ?? '—' }}</p>
            </div>

            <div class="flex items-start gap-4 mb-6">
                <img
                    src="{{ $profile?->photo_url ?? asset('assets/images/default-avatar.png') }}"
                    alt=""
                    class="size-16 rounded-xl object-cover bg-zinc-100 border border-zinc-200 shrink-0"
                />
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-500">Paid by</p>
                    <h2 class="text-lg font-bold text-zinc-900 uppercase">
                        {{ $profile?->full_name ?? '—' }}
                    </h2>
                    @if ($batch)
                        <p class="text-sm text-zinc-600 mt-0.5">
                            {{ $batch->name }} <span class="font-mono text-zinc-400">({{ $batch->code }})</span>
                        </p>
                    @endif
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Receipt No.</dt>
                    <dd class="mt-1 font-mono font-semibold text-zinc-800">{{ $payment->payment_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Payment Method</dt>
                    <dd class="mt-1 font-semibold text-zinc-800">{{ $payment->payment_method?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Trx ID</dt>
                    <dd class="mt-1 font-mono font-semibold text-zinc-800">{{ $payment->gateway_trx_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Status</dt>
                    <dd class="mt-1">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold {{ $completed ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $payment->status?->label() ?? '—' }}
                        </span>
                    </dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Paid at</dt>
                    <dd class="mt-1 font-semibold text-zinc-800">{{ $paidAt ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Verified footer --}}
        <div class="px-6 py-3 border-t border-zinc-100 bg-zinc-50 flex items-center justify-between text-xs text-zinc-500">
            <span>Verified at <strong class="text-zinc-700">{{ now()->format('d M Y, h:i A') }}</strong></span>
            <span>© {{ date('Y') }} University of Dhaka</span>
        </div>
    </div>

    <p class="mt-6 text-center text-xs text-zinc-500 leading-relaxed">
        This page is reached only through the QR code on a legitimate payment receipt.<br>
        It shows a compact summary for verification — it is not the full receipt.
    </p>
</div>

</body>
</html>
