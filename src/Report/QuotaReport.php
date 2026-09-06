<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Report;

/**
 * `agy --print=/usage`, as far as it can be read.
 *
 * `available` is false when the output could not be recognised. Nothing is
 * guessed in that case: no percentage, no reset time, only the raw text and
 * whatever the CLI wrote to stderr.
 */
final readonly class QuotaReport
{
    /** @param list<QuotaEntry> $entries */
    public function __construct(
        public bool $available,
        public array $entries,
        public string $text,
        public string $diagnostics = '',
    ) {
    }

    public static function unavailable(string $text = '', string $diagnostics = ''): self
    {
        return new self(false, [], $text, $diagnostics);
    }

    public function entryFor(string $group): ?QuotaEntry
    {
        foreach ($this->entries as $entry) {
            if (strcasecmp($entry->group, $group) === 0) {
                return $entry;
            }
        }

        return null;
    }
}
