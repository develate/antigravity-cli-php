<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Event;

/**
 * An event this SDK has no type for.
 *
 * A newer CLI adding an event is not an error, so the payload is carried
 * through unchanged instead of being dropped or rejected.
 */
final readonly class UnknownEvent implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(private string $name, private array $raw)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
