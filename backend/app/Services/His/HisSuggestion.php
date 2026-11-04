<?php

namespace App\Services\His;

/**
 * A POSSIBLY applicable activity. It is never persisted and never implies
 * the activity was performed: the nurse must confirm it as a HIS line.
 */
final class HisSuggestion
{
    public function __construct(
        public readonly string $catalogItemId,
        public readonly string $label,
        public readonly string $code,
        public readonly ?string $diagnosisTypeSuggested,
        public readonly ?string $labSuggested,
        public readonly string $verificationStatus,
        public readonly string $source,
        public readonly string $rationale,
        public readonly string $ruleCode,
        public readonly string $catalogVersion,
    ) {}
}
