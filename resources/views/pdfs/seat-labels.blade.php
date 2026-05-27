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
    table { border-collapse: collapse; }
    h1, h2, h3, p { margin: 0; padding: 0; }
    .tag-table td { border: 1px solid #000; padding: 4px; }
</style>
</head>
<body>

@php
    $chunks    = $assignments->chunk(12);
    $firstPage = true;
@endphp

@foreach ($chunks as $chunk)

    @if (! $firstPage)
        <pagebreak />
    @endif
    @php $firstPage = false; @endphp

    {{-- Outer 2-column grid for this page --}}
    <table width="100%">
        @foreach ($chunk->chunk(2) as $pair)
            <tr>
                @foreach ($pair as $assignment)
                    @php $photoPath = $assignment->student?->photo_path; @endphp
                    <td width="47%" style="padding:5px; vertical-align:top;">
                        <table width="320" style="border:1px solid #000; font-size:20px; height:190px;" class="tag-table">
                            <tr>
                                <td width="60" style="vertical-align:middle; text-align:center;">
                                    @if ($photoPath && file_exists($photoPath))
                                        <img src="{{ $photoPath }}" style="height:50px; width:50px;">
                                    @else
                                        <div style="height:50px; width:50px; border:1px solid #ccc; display:inline-block;"></div>
                                    @endif
                                </td>
                                <td style="font-size:10px; font-weight:bolder; vertical-align:middle;">
                                    Executive MBA Program<br>
                                    Faculty of Business Studies<br>
                                    University of Dhaka
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="font-size:15px; text-transform:capitalize;">
                                    <b>Name: {{ $assignment->student?->full_name ?? '—' }}</b>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="font-size:20px; text-transform:capitalize;">
                                    <b>Roll : &nbsp;{{ $assignment->roll }}</b>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach

                {{-- Fill empty cell if odd number in pair --}}
                @if ($pair->count() === 1)
                    <td width="47%"></td>
                @endif
            </tr>
            <tr>
                <td height="30" colspan="2"></td>
            </tr>
        @endforeach

    </table>

@endforeach

</body>
</html>
