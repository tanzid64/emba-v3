<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 12px;
            color: #000;
        }

        table {
            border-collapse: collapse;
        }

        .main-wrapper {
            border: 1px solid #000;
            padding: 14px;
            height: 100%;
        }

        .info-table td {
            border: 1px solid #777;
            padding: 7px 8px;
            font-size: 12px;
        }

        .info-table .label {
            width: 22%;
            background: #f2f2f2;
            font-weight: bold;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
        }

        .exam-box {
            border: 1.5px solid #000;
            padding: 10px;
            margin-top: 12px;
        }

        .instructions li {
            margin-bottom: 6px;
            line-height: 1.5;
            text-align: justify;
            font-size: 11px;
        }

        .footer-text {
            text-align: center;
            font-size: 9px;
            margin-top: 15px;
        }


        @page {
            header: page-header;
            footer: page-footer;
        }
    </style>
</head>

@php
    $logoPath = public_path('assets/logo/logo.jpg');
    $vivaBoard = $rollAssignment?->vivaBoard;
    $vivaDate = $batch?->admissionSetting?->viva_date;
@endphp

<body>
    <htmlpageheader name="page-header" class="page-header">

        <table width="100%"
            style="
               font-size:10px;
               margin:10px;
               padding-top:5px;
               padding-bottom:5px;">

            <tr>

                <td width="20%">
                    <strong>Application ID:</strong>
                    {{ $student->application_id }}
                </td>

                <td width="25%" align="center">
                    <strong>Roll:</strong>
                    {{ $rollAssignment?->roll ?? 'Pending' }}
                </td>

                <td width="35%" align="center">
                    <strong>Batch:</strong>
                    {{ $batch?->name ?? 'N/A' }}
                </td>

                <td width="20%" align="right">
                    {{ now()->format('d M Y h:i A') }}
                </td>

            </tr>

        </table>

    </htmlpageheader>

    <div class="main-wrapper">

        {{-- HEADER --}}
        <table width="100%">
            <tr>

                {{-- Logo --}}
                <td width="18%" align="left">
                    @if ($logoPath && file_exists($logoPath))
                        <img src="{{ $logoPath }}" style="width:80px; height:90px;" alt="Logo">
                    @endif
                </td>

                {{-- University Info --}}
                <td width="57%" align="center">

                    <div style="font-size:18px; font-weight:bold; line-height:1.4;">
                        Executive MBA Program
                    </div>

                    <div style="font-size:15px; line-height:1.4;">
                        Faculty of Business Studies
                    </div>

                    <div style="font-size:15px; line-height:1.4;">
                        University of Dhaka
                    </div>

                    <div style="margin-top:10px; font-size:26px; font-weight:bold;">
                        VIVA ADMIT CARD
                    </div>

                </td>

                {{-- Photo --}}
                <td width="25%" align="right">
                    @if ($student->photo_path && file_exists($student->photo_path))
                        <img src="{{ $student->photo_path }}" style="width:100px; height:100px; border:1px solid #000;"
                            alt="Student Photo">
                    @endif
                </td>

            </tr>
        </table>

        {{-- Session --}}
        <div class="section-title" style="margin-top:10px;">
            Viva Voce - {{ $batch?->name ?? 'N/A' }}
        </div>

        {{-- Candidate Information --}}
        <table width="100%" class="info-table" style="margin-top:15px;">

            <tr>
                <td class="label">Applicant Name</td>
                <td colspan="3">
                    <strong>{{ $student->full_name }}</strong>
                </td>
            </tr>

            <tr>
                <td class="label">Application ID</td>
                <td>
                    <strong>{{ $student->application_id }}</strong>
                </td>

                <td class="label">Roll Number</td>
                <td>
                    <strong>{{ $rollAssignment?->roll ?? 'Not Generated' }}</strong>
                </td>
            </tr>

            <tr>
                <td class="label">Father's Name</td>
                <td>
                    {{ $student->father_name ?? '—' }}
                </td>

                <td class="label">Mother's Name</td>
                <td>
                    {{ $student->mother_name ?? '—' }}
                </td>
            </tr>

            <tr>
                <td class="label">Mobile Number</td>
                <td>
                    {{ $student->mobile }}
                </td>

                <td class="label">Program</td>
                <td>
                    Executive MBA
                </td>
            </tr>

        </table>

        {{-- Viva Information --}}
        <div class="exam-box">

            <table width="100%">

                <tr>
                    <td width="25%">
                        <strong>Date of Viva</strong>
                    </td>

                    <td width="75%">
                        :
                        @if ($vivaDate)
                            {{ is_array($vivaDate) ? $vivaDate['formatted'] : $vivaDate }}
                        @else
                            Not Generated
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:8px;">
                        <strong>Viva Board</strong>
                    </td>

                    <td style="padding-top:8px;">
                        :
                        @if ($vivaBoard)
                            {{ $vivaBoard->board_name }}
                        @else
                            Not Assigned
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:8px;">
                        <strong>Viva Center</strong>
                    </td>

                    <td style="padding-top:8px;">
                        :
                        @if ($vivaBoard && ($vivaBoard->center_name || $vivaBoard->room_name))
                            {{ $vivaBoard->center_name }}
                            @if ($vivaBoard->room_name)
                                - {{ $vivaBoard->room_name }}
                            @endif
                            @if ($vivaBoard->center_no)
                                ({{ $vivaBoard->center_no }})
                            @endif,
                        @endif
                        Faculty of Business Studies,
                        University of Dhaka
                    </td>
                </tr>

            </table>

        </div>

        {{-- Instructions --}}
        <div style="margin-top:18px;">

            <div style="font-weight:bold; margin-bottom:8px;">
                Instructions for Candidates:
            </div>

            <ol class="instructions">

                <li>
                    Candidates must bring this viva admit card to the viva board and produce it when requested by the
                    board members.
                </li>

                <li>
                    Bring all original academic certificates, transcripts and the National ID card along with one set of
                    photocopies for verification.
                </li>

                <li>
                    Report to your assigned viva board at least 30 minutes before the scheduled time.
                </li>

                <li>
                    Use of mobile phones or any electronic devices is not permitted inside the viva board room.
                </li>

                <li>
                    Maintain decorum and follow all instructions provided by the board members and the admission office.
                </li>

                <li>
                    Candidates who fail to appear before the viva board on the scheduled date and time may be excluded
                    from consideration.
                </li>

            </ol>

        </div>

        {{-- Verification QR --}}
        @if (! empty($verifyUrl))
            <div style="text-align:center; margin-top:30px;">
                <barcode code="{{ $verifyUrl }}" type="QR" size="1.0" error="L" />
                <p style="font-size:9px; color:#666; margin-top:4px;">Scan to verify</p>
            </div>
        @endif
    </div>
</body>

</html>
