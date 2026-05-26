<?php

namespace App\Services;

use App\Models\AdmissionSetting;
use App\Models\Application;
use App\Models\Batch;
use Illuminate\Support\Facades\DB;

class AdmissionNumberingService
{
    public function nextApplicationNumber(Batch $batch): string
    {
        $next = $this->nextSequence(
            batchId: $batch->id,
            startColumn: 'application_number_start_from',
            applicationColumn: 'application_number',
        );

        return $batch->code.'-'.$next;
    }

    public function nextRollNumber(Application $application): string
    {
        if ($application->roll_number !== null) {
            return $application->roll_number;
        }

        $next = $this->nextSequence(
            batchId: $application->batch_id,
            startColumn: 'roll_number_start_from',
            applicationColumn: 'roll_number',
        );

        return (string) $next;
    }

    private function nextSequence(int $batchId, string $startColumn, string $applicationColumn): int
    {
        return DB::transaction(function () use ($batchId, $startColumn, $applicationColumn) {
            $setting = AdmissionSetting::where('batch_id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            $startFrom = (int) $setting->{$startColumn};

            $currentMax = Application::where('batch_id', $batchId)
                ->whereNotNull($applicationColumn)
                // Roll numbers are only assigned to submitted applications (which have an application_number).
                // Excluding rows without application_number prevents stray test/admin-tool rows from advancing the counter.
                ->when($applicationColumn === 'roll_number', fn ($q) => $q->whereNotNull('application_number'))
                ->pluck($applicationColumn)
                ->map(fn (string $raw): ?int => self::extractSequenceInt($raw, $applicationColumn))
                ->filter()
                ->max() ?? 0;

            return max($startFrom, $currentMax + 1);
        });
    }

    public static function extractSequenceInt(string $value, string $column): ?int
    {
        if ($column === 'roll_number') {
            return ctype_digit($value) ? (int) $value : null;
        }

        // application_number is stored as "{BATCH_CODE}-{INT}". Take the substring after the last dash.
        $dashPos = strrpos($value, '-');
        if ($dashPos === false) {
            return null;
        }
        $tail = substr($value, $dashPos + 1);

        return ctype_digit($tail) ? (int) $tail : null;
    }
}
