<?php

namespace App\Support;

use App\Enum\BatchStatusEnum;
use App\Models\Batch;

class CurrentBatch
{
    public const SESSION_KEY = 'admin.current_batch_id';

    /**
     * Resolve the currently-active batch id for the admin session.
     * Falls back to the open admission batch when nothing is set.
     */
    public static function id(): ?int
    {
        $id = session(self::SESSION_KEY);

        if ($id && Batch::whereKey($id)->exists()) {
            return (int) $id;
        }

        return static::activeFallback()?->id;
    }

    public static function get(): ?Batch
    {
        $id = self::id();

        return $id ? Batch::find($id) : null;
    }

    public static function set(?int $batchId): void
    {
        if ($batchId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session()->put(self::SESSION_KEY, $batchId);
    }

    /**
     * Returns the currently open batch as the implicit default.
     */
    public static function activeFallback(): ?Batch
    {
        return Batch::where('status', BatchStatusEnum::OPEN)->first()
            ?? Batch::orderByDesc('admission_year')->first();
    }
}
