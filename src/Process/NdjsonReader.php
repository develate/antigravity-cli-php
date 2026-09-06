<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Process;

/**
 * Reassembles NDJSON lines from arbitrarily fragmented process output.
 */
final class NdjsonReader
{
    private string $buffer = '';

    /** @return list<string> */
    public function push(string $chunk): array
    {
        if ($chunk === '') {
            return [];
        }

        $this->buffer .= $chunk;
        $lines = preg_split('/\r?\n/', $this->buffer) ?: [];
        $this->buffer = array_pop($lines) ?? '';

        return array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
    }

    /**
     * Whatever is left when the process ends without a trailing newline.
     */
    public function finish(): ?string
    {
        $line = trim($this->buffer);
        $this->buffer = '';

        return $line === '' ? null : $line;
    }
}
