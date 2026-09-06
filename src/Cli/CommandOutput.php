<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Cli;

final readonly class CommandOutput
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }

    /** @return list<string> non-empty stdout lines */
    public function lines(): array
    {
        return array_values(array_filter(
            array_map('rtrim', preg_split('/\r?\n/', $this->stdout) ?: []),
            static fn (string $line): bool => trim($line) !== '',
        ));
    }
}
