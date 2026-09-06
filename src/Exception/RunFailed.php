<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Exception;

/**
 * The CLI ran, but the turn itself ended unsuccessfully.
 *
 * Antigravity reports this in the `result` event and still exits 0 in some
 * cases, so the exit code alone cannot be trusted to detect it.
 */
final class RunFailed extends AntigravityException
{
    public function __construct(
        public readonly string $status,
        string $message = '',
    ) {
        parent::__construct($message === ''
            ? sprintf('Antigravity ended the turn with status "%s".', $status)
            : $message);
    }
}
