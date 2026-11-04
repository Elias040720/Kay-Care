<?php

namespace App\Services\His;

use App\Models\Encounter;
use App\Models\HisCatalogVersion;

/**
 * Picks the catalog version in force on the encounter's local date, so a
 * historical encounter is coded with the catalog that applied at that time.
 */
class HisCatalogResolver
{
    public function forDate(string $date): ?HisCatalogVersion
    {
        return HisCatalogVersion::query()
            ->effectiveOn($date)
            ->orderByDesc('valid_from')
            ->orderByDesc('created_at')
            ->first();
    }

    public function forEncounter(Encounter $encounter): ?HisCatalogVersion
    {
        return $this->forDate($encounter->localOccurredAt()->toDateString());
    }
}
