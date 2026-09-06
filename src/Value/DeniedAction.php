<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

/**
 * An action the CLI's own permission rules refused during the turn.
 *
 * Sandboxed `accept-edits` runs report these instead of prompting, which is the
 * only signal a caller gets that the agent wanted to do something it could not.
 */
final readonly class DeniedAction
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $action,
        public string $displayName,
        public array $raw = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $action = $data['action'] ?? '';
        $displayName = $data['display_name'] ?? $data['displayName'] ?? '';

        return new self(
            is_string($action) ? $action : '',
            is_string($displayName) ? $displayName : '',
            $data,
        );
    }
}
