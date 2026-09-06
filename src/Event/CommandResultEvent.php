<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Event;

/**
 * The output of a standalone slash command such as `/usage`.
 *
 * These are reports the CLI answers itself. They carry no conversation, so they
 * are kept out of the conversation stream rather than folded into a turn.
 */
final readonly class CommandResultEvent implements Event
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $command,
        public array $data,
        private array $raw,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $command = $payload['command'] ?? [];
        $command = is_array($command) ? $command : [];
        $name = $command['name'] ?? '';

        return new self(
            command: is_string($name) ? $name : '',
            data: is_array($command['data'] ?? null) ? $command['data'] : [],
            raw: $payload,
        );
    }

    public function name(): string
    {
        return 'command_result';
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
