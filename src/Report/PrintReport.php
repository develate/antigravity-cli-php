<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Report;

/**
 * The output of a standalone slash command.
 *
 * The raw text is kept whatever it looks like: these reports are meant for a
 * terminal, their layout is not a contract, and a caller that cannot parse one
 * is better served by showing it verbatim than by a confident wrong reading.
 */
final readonly class PrintReport
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $command,
        public string $text,
        public array $data = [],
        public string $diagnostics = '',
        public ?int $exitCode = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }
}
