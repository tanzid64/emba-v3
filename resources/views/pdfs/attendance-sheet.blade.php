<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 14px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        p {
            margin: 0;
            padding: 0;
        }

        .bordered td,
        .bordered th {
            border: 1px solid #000;
            padding: 4px 6px;
        }

        .summary td {
            border: 1px solid #000;
            padding: 4px 8px;
        }

        @page {
            header: page-header;
            footer: page-footer;
        }
    </style>
</head>

<body>

    @php
        $chunks = $students->chunk(12);
        $totalChunks = $chunks->count();
        $globalIndex = 0;
    @endphp

    <htmlpageheader name="page-header">
        <table width="100%" align="center">
            <tr>
                <td colspan="5" align="center">
                    <h5>
                        <span style="font-size:12px;">
                            Executive MBA Program, Admission Test<br>
                            Faculty of Business Studies, University of Dhaka
                        </span><br>
                        Exam Center: {{ $center->center_name }}<br>
                        Room No.: {{ $center->room_name }} ({{ $students->count() }} students)<br>
                        <b><u>Attendance Sheet</u></b>
                    </h5>
                </td>
            </tr>
        </table>
    </htmlpageheader>

    @foreach ($chunks as $chunkIndex => $chunk)
        @if ($chunkIndex > 0)
            <pagebreak />
        @endif

        {{-- ═══ STUDENT TABLE ═══ --}}
        <table width="100%" align="center" class="bordered">
            <tr style="font-size:16px; font-weight:bolder;">
                <th width="15">Sl</th>
                <th align="center" width="80">Photo</th>
                <th align="center" width="100">Roll</th>
                <th align="center">Name</th>
                <th align="center" width="200">Signature</th>
            </tr>

            @foreach ($chunk as $assignment)
                @php
                    $globalIndex++;
                    $even = $globalIndex % 2 === 0;
                @endphp
                <tr style="font-size:14px; {{ $even ? 'background-color:#f9f9f9;' : '' }}">
                    <td align="center" height="20">{{ $globalIndex }}</td>
                    <td align="center">
                        @if ($assignment->student?->photo_path)
                            <img src="{{ $assignment->student?->photo_path }}" style="width:60px; height:60px;">
                        @else
                            <div style="width:60px; height:60px; border:1px solid #ccc; display:inline-block;"></div>
                        @endif
                    </td>
                    <td align="center">{{ $assignment->roll }}</td>
                    <td>
                        {{ $assignment->student?->full_name ?? '—' }}<br>
                        {{ $assignment->student?->mobile ?? '' }}
                    </td>
                    <td></td>
                </tr>
            @endforeach
        </table>

        <br>

        {{-- ═══ FOOTER ═══ --}}
        <table width="100%">
            <tr>
                <td colspan="3" align="left">
                    <table class="summary">
                        <tr>
                            <td>Total Present :</td>
                            <td width="100"></td>
                        </tr>
                        <tr>
                            <td>Total Absent :</td>
                            <td width="100"></td>
                        </tr>
                        <tr>
                            <td>Total :</td>
                            <td width="100" style="font-size:20px; font-weight:bolder;" align="center"></td>
                        </tr>
                    </table>
                </td>
                <td colspan="2" align="right">
                    <table>
                        <tr>
                            <td>..........................................</td>
                        </tr>
                        <tr>
                            <td>Signature of the Invigilator</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach
    <htmlpagefooter name="page-footer">
        <table width="100%" style="font-size:10px;color:#555;">
            <tr>
                <td>This Attendance Sheet has been generated from Dean Office, FBS Online Software
                    ({{ date('Y-M-d h:i:sa') }})</td>
                <td align="right">Page {PAGENO} of {nbpg}</td>
            </tr>
        </table>
    </htmlpagefooter>
</body>

</html>
