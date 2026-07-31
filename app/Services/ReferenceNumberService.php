<?php

namespace App\Services;

use App\Models\Checklist;
use App\Models\Copy;
use InvalidArgumentException;

class ReferenceNumberService
{
    /**
     * Location => prefix map. Add new locations here only.
     */
    private const PREFIX_MAP = [
        'Central Depot' => 'CD',
        'Feedmill'      => 'FM',
    ];

    private const PAD_LENGTH = 4;

    /**
     * Generate the next reference number for a location, for the current year.
     * Format: {PREFIX}-{YY}-{NNNN}, e.g. CD-26-0001.
     * MUST be called from inside an active DB transaction (see CopyService::publish).
     */
    public function generate(string $location): string
    {
        $prefix = self::PREFIX_MAP[$location] ?? null;

        if ($prefix === null) {
            throw new InvalidArgumentException("Unsupported location [{$location}] for reference number generation.");
        }

        $year = now()->year;
        $yearShort = now()->format('y'); // 2-digit year, e.g. "26"

        // lockForUpdate() locks whatever row this query returns, blocking other
        // transactions from reading it until we commit/rollback. This serializes
        // concurrent publishes for the SAME prefix + year.
        //
        // The like pattern is scoped to "{prefix}-{yy}-" (not just "{prefix}-")
        // so that on the first publish of a new year, we don't accidentally
        // pick up last year's highest number under the same prefix — the
        // sequence resets to 0001 per prefix per year, by design.
        $last = Copy::where('reference_number', 'like', "{$prefix}-{$yearShort}-%")
            ->whereYear('created_at', $year)
            ->orderByRaw('CAST(SUBSTRING_INDEX(reference_number, "-", -1) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->first();

        $nextNumber = $last
            ? ((int) substr($last->reference_number, strrpos($last->reference_number, '-') + 1)) + 1
            : 1;

        return sprintf('%s-%s-%s', $prefix, $yearShort, str_pad((string) $nextNumber, self::PAD_LENGTH, '0', STR_PAD_LEFT));
        // Note: if a location ever exceeds 9999 in a single year, this naturally
        // becomes CD-26-10000 (5 digits) rather than throwing — acceptable, but
        // worth knowing so it's not mistaken for a bug later.
    }
}
