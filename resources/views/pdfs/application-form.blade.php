<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .bordered td,
        .bordered th {
            border: 0.5px solid #b0c8d8;
            padding: 5px 7px;
            vertical-align: top;
        }

        .section-heading td {
            font-size: 13px;
            font-weight: bold;
            background: #e8f0f7;
            padding: 5px 7px;
            border: 0.5px solid #b0c8d8;
        }

        .label {
            font-weight: bold;
            width: 22%;
        }

        .half-label {
            font-weight: bold;
            width: 14%;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
            padding: 0;
        }

        hr {
            border: none;
            border-top: 1px solid #555;
            margin: 8px 0;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .declaration {
            font-size: 10.5px;
            text-align: justify;
            line-height: 1.5;
            margin-top: 10px;
        }

        .footer-note {
            font-size: 10px;
            color: #444;
            margin-top: 6px;
        }

        .app-id-box {
            font-size: 15px;
            font-weight: bold;
            color: #c00;
        }
    </style>
</head>

<body>

    @php
        $logoPath = public_path('assets/logo/logo.jpg');
        $presentAddr = $data->addresses->firstWhere('type->value', 'present') ?? $data->addresses->first();
        $permanentAddr = $data->addresses->firstWhere('type->value', 'permanent') ?? $data->addresses->last();

        $formatAddr = function ($addr) use ($districts, $upazilas) {
            if (!$addr) {
                return '—';
            }
            $parts = array_filter([
                $addr->road,
                $upazilas[$addr->upazila_id] ?? null,
                $districts[$addr->district_id] ?? null,
                $addr->post_office ? 'P.O: ' . $addr->post_office : null,
                $addr->postal_code ? 'P.C: ' . $addr->postal_code : null,
            ]);
            return implode(', ', $parts);
        };
    @endphp

    {{-- ═══════════════ HEADER ═══════════════ --}}
    <table>
        <tr>
            <td width="20%"></td>
            <td width="60%" class="text-center">
                @if (file_exists($logoPath))
                    <img src="{{ $logoPath }}" width="60" height="66" alt="Logo">
                @endif
                <h2 style="font-size:13px; margin-top:4px;">Executive MBA Program</h2>
                <h3 style="font-size:10px; font-weight:normal;">Faculty of Business Studies</h3>
                <h3 style="font-size:10px; font-weight:normal;">University of Dhaka</h3>
                <h3 style="font-size:10px; margin-top:4px;"><i>Application Form (Applicant's Copy)</i></h3>
            </td>
            <td width="20%"></td>
        </tr>
    </table>

    <hr>

    {{-- ═══════════════ PHOTO · INFO · APP-ID ROW ═══════════════ --}}
    <table style="margin-bottom:6px;">
        <tr>
            {{-- Photo --}}
            <td width="18%" style="vertical-align:top; padding-right:8px;">

                <img src="{{ $data->photo_path }}"
                    style="width:100px; height:100px; border:1px solid #000; object-fit:cover;" alt="Photo">
            </td>

            {{-- Application ID & Batch --}}
            <td width="82%" style="vertical-align:top; text-align:right; padding-left:8px;">
                <p style="font-size:11px;"><b>Application ID:</b> {{ $data->application_id }}</p>
                @if ($data->batch)
                    <p style="font-size:10px; color:#444;">Batch: {{ $data->batch->name }} ({{ $data->batch->code }})
                    </p>
                @endif
                <p style="font-size:10px; color:#444;">Applied: {{ $data->applied_at?->format('d F Y') }}</p>
            </td>
        </tr>
    </table>

    <hr>

    {{-- ═══════════════ PERSONAL INFORMATION ═══════════════ --}}
    <table class="bordered" style="margin-bottom:8px;">
        <tr class="section-heading">
            <td colspan="4">Personal Information</td>
        </tr>
        <tr>
            <td class="label">Name of the Applicant</td>
            <td colspan="3">{{ $data->full_name }}</td>
        </tr>
        <tr>
            <td class="half-label">Father's Name</td>
            <td width="36%">{{ $data->father_name ?? '—' }}</td>
            <td class="half-label">Mother's Name</td>
            <td>{{ $data->mother_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Present Address</td>
            <td colspan="3">{{ $formatAddr($presentAddr) }}</td>
        </tr>
        <tr>
            <td class="label">Permanent Address</td>
            <td colspan="3">{{ $formatAddr($permanentAddr) }}</td>
        </tr>
        <tr>
            <td class="half-label">Date of Birth</td>
            <td>{{ $data->date_of_birth ? \Carbon\Carbon::parse($data->date_of_birth)->format('d M Y') : '—' }}</td>
            <td class="half-label">Blood Group</td>
            <td>{{ $data->blood_group?->label() ?? '—' }}</td>
        </tr>
        <tr>
            <td class="half-label">Mobile No.</td>
            <td>{{ $data->mobile }}</td>
            <td class="half-label">Email</td>
            <td>{{ $data->email }}</td>
        </tr>
        <tr>
            <td class="half-label">Gender</td>
            <td>{{ $data->gender?->label() ?? '—' }}</td>
            <td class="half-label">Marital Status</td>
            <td>{{ $data->marital_status?->label() ?? '—' }}</td>
        </tr>
        <tr>
            <td class="half-label">Religion</td>
            <td>{{ $data->religion?->label() ?? '—' }}</td>
            <td class="half-label">Nationality</td>
            <td>{{ $data->nationality ?? '—' }}</td>
        </tr>
    </table>

    {{-- ═══════════════ ACADEMIC INFORMATION ═══════════════ --}}
    <table class="bordered" style="margin-bottom:8px;">
        <tr class="section-heading">
            <td colspan="6">Academic Information</td>
        </tr>
        <tr>
            <th class="bordered" style="width:22%;">Certificate / Degree</th>
            <th class="bordered" style="width:18%;">Group / Subject</th>
            <th class="bordered" style="width:22%;">Board / University</th>
            <th class="bordered" style="width:14%;">GPA / Result</th>
            <th class="bordered" style="width:12%;">Passing Year</th>
            <th class="bordered" style="width:12%;">Duration</th>
        </tr>
        @forelse ($data->degrees as $degree)
            <tr>
                <td>{{ $degree->name }}</td>
                <td>{{ $degree->major ?? '—' }}</td>
                <td>{{ $degree->institute ?? '—' }}</td>
                <td>
                    {{ $degree->result ?? '—' }}
                    @if ($degree->scale)
                        <span style="color:#666;"> / {{ $degree->scale }}</span>
                    @endif
                </td>
                <td>{{ $degree->passing_year ?? '—' }}</td>
                <td>{{ $degree->duration ? $degree->duration . ' Yrs' : '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align:center; color:#888;">No academic records</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="5" style="font-weight:bold;">Total Years of Schooling</td>
            <td>{{ $data->total_schooling_years ? $data->total_schooling_years . ' Yrs' : '—' }}</td>
        </tr>
    </table>

    {{-- ═══════════════ WORK EXPERIENCE ═══════════════ --}}
    <table class="bordered" style="margin-bottom:8px;">
        <tr class="section-heading">
            <td colspan="3">Work Experience</td>
        </tr>
        <tr>
            <th class="bordered" style="width:45%;">Name of the Organization</th>
            <th class="bordered" style="width:35%;">Designation / Position</th>
            <th class="bordered" style="width:20%;">Duration</th>
        </tr>
        @forelse ($data->experiences as $exp)
            <tr>
                <td>{{ $exp->organization }}</td>
                <td>{{ $exp->designation ?? '—' }}</td>
                <td>{{ $exp->duration ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" style="text-align:center; color:#888;">No experience records</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="2" style="font-weight:bold;">Total Years of Experience</td>
            <td>{{ $data->total_experience_years ? $data->total_experience_years . ' Yrs' : '—' }}</td>
        </tr>
    </table>

    {{-- ═══════════════ PAYMENT STATUS ═══════════════ --}}
    @if ($data->payment_status)
        <table class="bordered" style="margin-bottom:8px;">
            <tr class="section-heading">
                <td colspan="4">Payment Information</td>
            </tr>
            <tr>
                <td class="half-label">Status</td>
                <td><b style="color:green;">PAID</b></td>
                <td class="half-label">Transaction ID</td>
                <td>{{ $data->trx_id ?? '—' }}</td>
            </tr>
            <tr>
                <td class="half-label">Payment Method</td>
                <td>{{ $data->pay_type?->label() ?? '—' }}</td>
                <td class="half-label">Paid At</td>
                <td>{{ $data->paid_at['formatted'] ?? '—' }}</td>
            </tr>
        </table>
    @endif

    {{-- ═══════════════ DECLARATION ═══════════════ --}}
    <p class="declaration">
        I declare that the information provided in this form is correct, true, and complete to the best of my knowledge
        and belief. If any information is found to be false, incorrect, or incomplete, or if any ineligibility is
        detected before or after the examination, the University reserves the right to take any action against me,
        including cancellation of my candidature.
    </p>
    <hr>
    <table style="margin-top:6px;">
        <tr>
            <td width="80%">
                <p class="app-id-box">Your Application ID is: {{ $data->application_id }}</p>
                <p class="footer-note" style="margin-top:4px;">
                    Please keep this ID safe, as you will need it to pay the application fee through bKash Mobile
                    Banking.
                </p>
                <p class="footer-note" style="margin-top:8px;">
                    &copy; {{ date('Y') }}, Faculty of Business Studies, Executive MBA Program, University of
                    Dhaka.
                    All rights reserved.
                </p>
            </td>
            <td width="20%" style="text-align:right; vertical-align:bottom;">
                <p style="font-size:9px; color:#888;">Generated: {{ now()->format('d M Y, h:i A') }}</p>
            </td>
        </tr>
    </table>
</body>

</html>
