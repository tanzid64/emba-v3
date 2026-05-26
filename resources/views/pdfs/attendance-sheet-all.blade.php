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

        .center-header {
            background-color: #e0e0e0;
            font-weight: bold;
            padding: 8px;
        }
    </style>
</head>

<body>

    @php
        $firstPage = true;
        $pageCount = 0;
    @endphp

    @foreach ($centers as $centerGroup)
        @php
            $center_no = $centerGroup['center_no'];
            $center_name = $centerGroup['center_name'];
            $rooms = $centerGroup['rooms'];
        @endphp

        {{-- ═══ CENTER HEADER ═══ --}}
        <div class="center-header" style="margin-top: 20px;">
            Center {{ $center_no }}: {{ $center_name }}
        </div>

        {{-- ═══ ROOM SUMMARY ═══ --}}
        <table width="100%" style="margin-bottom: 20px;">
            <tr style="background-color: #f5f5f5;">
                <th align="left" style="border: 1px solid #ddd; padding: 6px;">Room</th>
                <th align="center" style="border: 1px solid #ddd; padding: 6px;">Student Count</th>
            </tr>
            @foreach ($rooms as $room)
                <tr>
                    <td style="border: 1px solid #ddd; padding: 6px;">{{ $room->room_name }}</td>
                    <td align="center" style="border: 1px solid #ddd; padding: 6px;">{{ $room->student_count }}</td>
                </tr>
            @endforeach
        </table>

        {{-- ═══ DETAILED SHEETS FOR EACH ROOM ═══ --}}
        @foreach ($rooms as $room)
            @php
                $roomStudents = $room->students;
                $chunks = $roomStudents->chunk(12);
                $totalChunks = $chunks->count();
                $globalIndex = 0;
            @endphp

            @foreach ($chunks as $chunkIndex => $chunk)
                @if (!$firstPage)
                    <pagebreak />
                @endif
                @php
                    $firstPage = false;
                    $pageCount++;
                @endphp

                {{-- ═══ HEADER ═══ --}}
                <table width="100%" align="center">
                    <tr>
                        <td colspan="5" align="center">
                            <h5>
                                <span style="font-size:12px;">
                                    Executive MBA Program, Admission Test<br>
                                    Faculty of Business Studies, University of Dhaka
                                </span><br>
                                Exam Center: {{ $center_name }}<br>
                                Room No.: {{ $room->room_name }} ({{ $room->student_count }} students)<br>
                                <b><u>Attendance Sheet</u></b>
                            </h5>
                        </td>
                    </tr>
                </table>

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
                            $photoPath = $assignment->student?->photo_path;
                        @endphp
                        <tr style="font-size:14px; {{ $even ? 'background-color:#f9f9f9;' : '' }}">
                            <td align="center" height="20">{{ $globalIndex }}</td>
                            <td align="center">
                                @if ($photoPath && file_exists($photoPath))
                                    <img src="{{ $photoPath }}" style="width:60px; height:60px;">
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
                                    <td width="100" style="font-size:20px; font-weight:bolder;" align="center">
                                        {{ $chunk->count() }}</td>
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

                <p style="text-align:right; padding:0; margin:0;">
                    Page {{ $pageCount }}
                </p>
                <h5 style="font-size:8px; text-align:center; padding-top:10px;">
                    This Attendance Sheet has been generated from Dean Office, FBS Online Software and downloaded
                    through internet ({{ date('Y-M-d h:i:sa') }}).
                </h5>
            @endforeach
        @endforeach
    @endforeach

</body>

</html>
