<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Application — {{ $application->application_number }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-100 antialiased text-zinc-800 font-sans">

@php
    $applicant = $application->applicant;
    $profile = $applicant?->profile;
    $batch = $application->batch;
    $appliedAt = $application->getRawOriginal('applied_at')
        ? \Carbon\Carbon::parse($application->getRawOriginal('applied_at'))->format('d M Y, h:i A')
        : null;
    $paid = in_array($application->payment_status, [
        \App\Enums\PaymentStatusEnum::PAID,
        \App\Enums\PaymentStatusEnum::COMPLETED,
    ], true);
@endphp

<div class="mx-auto max-w-2xl px-4 py-10 sm:py-16">
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <img src="{{ asset('assets/logo/logo.jpg') }}" alt="University of Dhaka" class="size-12 rounded">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-brand">Application Verification</p>
            <p class="text-sm font-semibold text-zinc-700">Executive MBA, Faculty of Business Studies — University of Dhaka</p>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden">
        {{-- Authentic banner --}}
        <div class="flex items-center gap-3 px-6 py-4 bg-emerald-50 border-b border-emerald-200">
            <span class="inline-flex items-center justify-center size-10 rounded-full bg-emerald-500 text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
            </span>
            <div>
                <h1 class="text-base font-bold text-emerald-900">Authentic application</h1>
                <p class="text-sm text-emerald-800">This application is on record with the EMBA admission office.</p>
            </div>
        </div>

        {{-- Applicant summary --}}
        <div class="px-6 py-6">
            <div class="flex items-start gap-4">
                <img
                    src="{{ $profile?->photo_url ?? asset('assets/images/default-avatar.png') }}"
                    alt=""
                    class="size-20 rounded-xl object-cover bg-zinc-100 border border-zinc-200 shrink-0"
                />
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-500">Applicant</p>
                    <h2 class="text-xl font-bold text-zinc-900 uppercase">
                        {{ $profile?->full_name ?? 'Profile not completed' }}
                    </h2>
                    <p class="font-mono text-sm text-zinc-600 mt-1">{{ $application->application_number }}</p>
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Batch</dt>
                    <dd class="mt-1 font-semibold text-zinc-800">
                        {{ $batch?->name ?? '—' }}
                        @if ($batch?->code)
                            <span class="font-mono text-zinc-500">({{ $batch->code }})</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Submitted</dt>
                    <dd class="mt-1 font-semibold text-zinc-800">{{ $appliedAt ?? 'Not submitted' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Application status</dt>
                    <dd class="mt-1">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700">
                            {{ $application->status?->label() ?? '—' }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-widest text-zinc-500">Payment</dt>
                    <dd class="mt-1">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold {{ $paid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $application->payment_status?->label() ?? '—' }}
                        </span>
                    </dd>
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
        This page is reached only through the QR code on a legitimate application form.<br>
        It shows a compact summary for verification — it is not the full application.
    </p>
</div>

</body>
</html>
