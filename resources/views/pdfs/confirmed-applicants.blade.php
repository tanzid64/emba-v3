<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 9px;
            color: #111;
        }

        h1, h2, p { margin: 0; padding: 0; }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 0;
        }

        .subtitle {
            text-align: center;
            font-size: 10px;
            font-style: italic;
            color: #4B5563;
            margin: 2px 0 10px 0;
        }

        .meta { width: 100%; margin-top: 4px; margin-bottom: 4px; }
        .meta td { padding: 2px 4px; }
        .meta .batch { font-weight: bold; }
        .meta .gen { text-align: right; color: #4B5563; }

        .summary {
            font-size: 9px;
            color: #374151;
            margin-bottom: 8px;
            padding: 4px 6px;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
        }

        table.results {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        table.results th {
            background: #2F1B72;
            color: #fff;
            font-weight: bold;
            border: 1px solid #1F2937;
            padding: 4px 5px;
            text-align: center;
        }

        table.results td {
            border: 1px solid #D1D5DB;
            padding: 3px 5px;
        }

        table.results tr.even td { background: #F3F4F6; }

        .center { text-align: center; }
        .name { text-transform: uppercase; }

        .footer {
            text-align: center;
            font-size: 8px;
            color: #6B7280;
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <h1 class="title">Executive MBA Program — Confirmed Applicants</h1>
    <p class="subtitle">Faculty of Business Studies, University of Dhaka</p>

    <table class="meta">
        <tr>
            <td class="batch">Batch: {{ $batch->name }} ({{ $batch->code }})</td>
            <td class="gen">Generated: {{ now()->format('d M Y, h:i A') }}</td>
        </tr>
    </table>

    <div class="summary">
        Total confirmed: {{ number_format($totalCount) }}
    </div>

    <table class="results">
        <thead>
            <tr>
                <th>SL</th>
                <th>Roll</th>
                <th>Application ID</th>
                <th>Name</th>
                <th>Father's Name</th>
                <th>Mother's Name</th>
                <th>Mobile</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
                @php $profile = $application->applicant?->profile; @endphp
                <tr class="{{ $loop->even ? 'even' : '' }}">
                    <td class="center">{{ $loop->iteration }}</td>
                    <td class="center">{{ $application->roll_number }}</td>
                    <td class="center">{{ $application->application_number }}</td>
                    <td class="name">{{ $profile?->full_name ?? '—' }}</td>
                    <td>{{ $profile?->father_name ?? '—' }}</td>
                    <td>{{ $profile?->mother_name ?? '—' }}</td>
                    <td>{{ $application->applicant?->phone_number ?? '—' }}</td>
                    <td>{{ $application->applicant?->email ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center" style="padding:14px; color:#6B7280;">
                        No confirmed applicants in this batch.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">
        Dean Office, FBS Online Software — generated {{ now()->format('Y-m-d H:i:s') }}
    </p>

</body>
</html>
