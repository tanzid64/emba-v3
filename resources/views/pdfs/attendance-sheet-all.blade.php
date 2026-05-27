<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 14px;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
            background: #e0e0e0;
            font-weight: bold;
            padding: 10px;
            text-align: center;
            font-size: 16px;
        }

        .center-logo {
            text-align: center;
            margin-bottom: 20px;
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

        @page {
            header: page-header;
            footer: page-footer;
        }
    </style>
</head>

<body>

    @php
        $firstPage = true;
    @endphp

    @foreach ($centers as $centerGroup)
        @if (!$firstPage)
            <pagebreak />
        @endif
        @php
            $firstPage = false;
            $center_no = $centerGroup['center_no'];
            $center_name = $centerGroup['center_name'];
            $rooms = $centerGroup['rooms'];
            $logoPath = public_path('assets/logo/logo.jpg');
        @endphp
        <div class="center-logo">
            @if ($logoPath)
                <img src="{{ $logoPath }}" />
            @endif
        </div>
        {{-- CENTER COVER PAGE --}}
        <div class="center-header" style="margin-top:30px;">

            <h3>Executive MBA Program, Admission Test</h3>
            <h4>Faculty of Business Studies, University of Dhaka</h4>
            <h2>Center {{ $center_no }}: {{ $center_name }}</h2>
        </div>

        <br><br>
        <h4 align="center">Room Summary & Capacity</h4>
        <table class="summary" align="center" style="width:60%; margin:20px auto;">
            <tr style="background:#f5f5f5;">
                <th>Room</th>
                <th>Student Count</th>
            </tr>
            @foreach ($rooms as $room)
                <tr>
                    <td>{{ $room->room_name }}</td>
                    <td align="center">{{ $room->student_count }}</td>
                </tr>
            @endforeach
        </table>

        <pagebreak />

        {{-- ROOM ATTENDANCE SHEETS --}}
        @foreach ($rooms as $room)
            @php
                $roomStudents = $room->students;
                $chunks = $roomStudents->chunk(12);
                $globalIndex = 0;
            @endphp

            @foreach ($chunks as $chunk)
                @if (!$loop->first || !$loop->parent->first)
                    <pagebreak />
                @endif

                {{-- Header --}}
                <table width="100%">
                    <tr>
                        <td align="center">
                            <h5>Executive MBA Program, Admission Test<br>
                                Faculty of Business Studies, University of Dhaka<br>
                                Exam Center: {{ $center_name }} | Room: {{ $room->room_name }}
                                ({{ $room->student_count }} students)
                                <br>
                                <b><u>Attendance Sheet</u></b>
                            </h5>
                        </td>
                    </tr>
                </table>

                {{-- Student Table --}}
                <table width="100%" class="bordered">
                    <tr style="font-weight:bold;">
                        <th width="15">Sl</th>
                        <th width="80">Photo</th>
                        <th width="100">Roll</th>
                        <th>Name & Mobile</th>
                        <th width="200">Signature</th>
                    </tr>
                    @foreach ($chunk as $index => $assignment)
                        @php
                            $globalIndex++;
                            $photoPath = $assignment->student?->photo_path;
                        @endphp
                        <tr style="height:65px; {{ $index % 2 == 0 ? 'background:#f9f9f9;' : '' }}">
                            <td align="center">{{ $globalIndex }}</td>
                            <td align="center">
                                @if ($photoPath && file_exists($photoPath))
                                    <img src="{{ $photoPath }}" style="width:60px;height:60px;">
                                @else
                                    <div style="width:60px;height:60px;border:1px solid #ccc;"></div>
                                @endif
                            </td>
                            <td align="center">{{ $assignment->roll }}</td>
                            <td>{{ $assignment->student?->full_name ?? '—' }}<br>{{ $assignment->student?->mobile ?? '' }}
                            </td>
                            <td></td>
                        </tr>
                    @endforeach
                </table>

                <br>

                {{-- Footer --}}
                <table width="100%">
                    <tr>
                        <td>
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
                                    <td align="center" style="font-size:18px;font-weight:bold;">{{ $chunk->count() }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td align="right">
                            .......................................... <br>
                            Signature of the Invigilator
                        </td>
                    </tr>
                </table>
            @endforeach
        @endforeach
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
