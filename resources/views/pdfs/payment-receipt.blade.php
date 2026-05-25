<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: sans-serif;
        font-size: 11px;
        color: #111;
        margin: 0;
        padding: 0;
    }
    h1, h2, h3, p { margin: 0; padding: 0; }
    table { width: 100%; border-collapse: collapse; }

    .header-table td { vertical-align: middle; padding: 0; }

    .institution { font-size: 15px; font-weight: bold; color: #2F1B72; }
    .institution-sub { font-size: 10px; color: #555; margin-top: 2px; }

    .receipt-title {
        text-align: center;
        background: #2F1B72;
        color: #fff;
        font-size: 13px;
        font-weight: bold;
        letter-spacing: 1px;
        padding: 7px 0;
        margin: 12px 0 14px;
    }

    .receipt-no {
        font-size: 10px;
        color: #888;
        text-align: right;
        margin-bottom: 10px;
    }

    .section-label {
        font-size: 9px;
        font-weight: bold;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #2F1B72;
        border-bottom: 1px solid #2F1B72;
        padding-bottom: 3px;
        margin-bottom: 6px;
    }

    .info-table td {
        padding: 4px 0;
        vertical-align: top;
    }
    .info-label {
        font-weight: bold;
        color: #444;
        width: 38%;
        font-size: 10.5px;
    }
    .info-colon { width: 4%; color: #888; }
    .info-value { font-size: 10.5px; color: #111; }

    .amount-box {
        background: #f0ebff;
        border: 1.5px solid #2F1B72;
        border-radius: 4px;
        text-align: center;
        padding: 10px;
        margin: 14px 0;
    }
    .amount-label { font-size: 10px; color: #555; margin-bottom: 2px; }
    .amount-value { font-size: 22px; font-weight: bold; color: #2F1B72; }
    .amount-currency { font-size: 12px; color: #555; margin-top: 2px; }

    .status-badge {
        display: inline-block;
        background: #d1fae5;
        color: #065f46;
        font-size: 10px;
        font-weight: bold;
        padding: 3px 10px;
        border-radius: 20px;
        border: 1px solid #6ee7b7;
    }

    .divider { border: none; border-top: 0.5px solid #ddd; margin: 12px 0; }

    .footer {
        margin-top: 20px;
        font-size: 9px;
        color: #888;
        text-align: center;
        line-height: 1.6;
    }
    .footer strong { color: #444; }

    .mono { font-family: monospace; font-size: 10px; }
</style>
</head>
<body>

@php
    $logoPath = public_path('assets/logo/logo.jpg');
    $bkashLogoPath = public_path('assets/images/bkash-logo.jpeg');

    // v3 schema bridge — keep the v2-style variable names the rest of the
    // template uses (`$student`, `$batch`, `$paidAt`) so the receipt design
    // stays untouched.
    $applicant = $payment->applicant;
    $profile   = $applicant?->profile;
    $batch     = $applicant?->batch;

    // application_number comes from the related Application when the payment
    // was made against one (actor_table = APPLICATION).
    $application = ($payment->actor_table === \App\Enums\PaymentActorEnum::APPLICATION && $payment->actor_id)
        ? \App\Models\Application::find($payment->actor_id)
        : null;

    $student = (object) [
        'full_name'      => $profile?->full_name ?? '—',
        'application_id' => $application?->application_number ?? '—',
        'mobile'         => $applicant?->phone_number ?? '—',
        'batch'          => $batch,
    ];

    $paidAtRaw = $payment->getRawOriginal('paid_at');
    $paidAt    = $paidAtRaw ? \Carbon\Carbon::parse($paidAtRaw)->format('d M Y, h:i A') : '—';
@endphp

{{-- Header --}}
<table class="header-table">
    <tr>
        <td style="width:60px;">
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" style="width:55px; height:55px; object-fit:contain;">
            @endif
        </td>
        <td style="padding-left:10px;">
            <p class="institution">Faculty of Business Studies</p>
            <p class="institution-sub">University of Dhaka</p>
            <p class="institution-sub">Executive MBA Programme</p>
        </td>
        <td style="text-align:right; vertical-align:top; padding-top:4px;">
            @if(file_exists($bkashLogoPath))
                <img src="{{ $bkashLogoPath }}" style="height:45px; max-width:80px; object-fit:contain;">
            @endif
        </td>
    </tr>
</table>

<div class="receipt-title">PAYMENT RECEIPT</div>

<p class="receipt-no">Receipt No: <strong>{{ $receiptNo }}</strong></p>

{{-- Amount Box --}}
<div class="amount-box">
    <p class="amount-label">Amount Paid</p>
    <p class="amount-value">Tk. {{ number_format($payment->amount) }}</p>
    <p class="amount-currency">Bangladeshi Taka (BDT) &nbsp;|&nbsp; {{ $purpose->label() }}</p>
</div>

{{-- Student Details --}}
<p class="section-label">Student Information</p>
<table class="info-table">
    <tr>
        <td class="info-label">Name</td>
        <td class="info-colon">:</td>
        <td class="info-value">{{ $student->full_name }}</td>
    </tr>
    <tr>
        <td class="info-label">Application ID</td>
        <td class="info-colon">:</td>
        <td class="info-value mono">{{ $student->application_id }}</td>
    </tr>
    <tr>
        <td class="info-label">Mobile</td>
        <td class="info-colon">:</td>
        <td class="info-value">{{ $student->mobile }}</td>
    </tr>
    <tr>
        <td class="info-label">Batch</td>
        <td class="info-colon">:</td>
        <td class="info-value">{{ $batch?->name ?? '—' }}</td>
    </tr>
</table>

<hr class="divider">

{{-- Transaction Details --}}
<p class="section-label">Transaction Details</p>
<table class="info-table">
    <tr>
        <td class="info-label">Payment Method</td>
        <td class="info-colon">:</td>
        <td class="info-value">bKash</td>
    </tr>
    <tr>
        <td class="info-label">bKash Trx ID</td>
        <td class="info-colon">:</td>
        <td class="info-value mono">{{ $payment->gateway_trx_id ?? '—' }}</td>
    </tr>
    <tr>
        <td class="info-label">bKash Payment ID</td>
        <td class="info-colon">:</td>
        <td class="info-value mono" style="font-size:9.5px;">{{ $payment->gateway_payment_id ?? '—' }}</td>
    </tr>
    <tr>
        <td class="info-label">Invoice No</td>
        <td class="info-colon">:</td>
        <td class="info-value mono" style="font-size:9.5px;">{{ $payment->metadata['invoice'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="info-label">Payment Date</td>
        <td class="info-colon">:</td>
        <td class="info-value">{{ $paidAt }}</td>
    </tr>
    <tr>
        <td class="info-label">Status</td>
        <td class="info-colon">:</td>
        <td class="info-value"><strong style="color:#065f46;">Completed</strong></td>
    </tr>
</table>

{{-- Footer --}}
<div class="footer">
    <p>This is a computer-generated receipt and does not require a signature.</p>
    <p>For queries, contact the EMBA Programme Office &nbsp;|&nbsp; Faculty of Business Studies, University of Dhaka</p>
    <p style="margin-top:4px; color:#bbb;">Generated on {{ now()->format('d M Y, h:i A') }}</p>
</div>

</body>
</html>
