<?php

declare(strict_types=1);

namespace Develate\AntigravityCli;

use Develate\AntigravityCli\Exception\InvalidOptions;

/**
 * What can differ from one turn to the next.
 */
final readonly class RunOptions
{
    /**
     * @param  float|null  $timeout  the SDK's own deadline, in seconds, for the whole process
     * @param  float|null  $printTimeout  the CLI's internal `--print-timeout`, in seconds
     * @param  array<string, mixed>|string|null  $schema  a JSON schema, or a path to one, for structured output
     */
    public function __construct(
        public ?float $timeout = null,
        public ?float $printTimeout = null,
        public array|string|null $schema = null,
    ) {
        if ($timeout !== null && $timeout <= 0) {
            throw new InvalidOptions('The timeout must be greater than zero.');
        }

        if ($printTimeout !== null && $printTimeout <= 0) {
            throw new InvalidOptions('The print timeout must be greater than zero.');
        }

        if (is_string($schema) && $schema === '') {
            throw new InvalidOptions('The schema must not be empty.');
        }
    }

    public function withTimeout(?float $timeout): self
    {
        return new self($timeout, $this->printTimeout, $this->schema);
    }

    public function withPrintTimeout(?float $printTimeout): self
    {
        return new self($this->timeout, $printTimeout, $this->schema);
    }

    /** @param array<string, mixed>|string|null $schema */
    public function withSchema(array|string|null $schema): self
    {
        return new self($this->timeout, $this->printTimeout, $schema);
    }
}
