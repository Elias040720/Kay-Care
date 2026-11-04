<?php

namespace App\Services\His;

final class HisValidationResult
{
    /**
     * @param  list<string>  $errors  blocking issues
     * @param  list<string>  $warnings  advisories (always shown)
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $warnings,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * Messages for the API "warnings" field: blocking issues first, prefixed
     * so a client that only reads "warnings" still sees them.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return array_merge(
            array_map(fn (string $e) => 'ERROR: '.$e, $this->errors),
            $this->warnings,
        );
    }
}
