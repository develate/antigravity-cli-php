<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tool;

use Develate\AntigravityCli\Value\StepState;
use Develate\AntigravityCli\Value\ToolError;

/**
 * One tool invocation, merged from every update to its step.
 */
final class ToolCall
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public readonly string $conversationId,
        public readonly int $stepIndex,
        public readonly string $name,
        public array $parameters = [],
        public StepState $state = StepState::Active,
        public ?ToolError $error = null,
        public ?float $durationSeconds = null,
    ) {
    }

    public function key(): string
    {
        return $this->conversationId.'#'.$this->stepIndex;
    }

    public function failed(): bool
    {
        return $this->state === StepState::Error || $this->error !== null;
    }

    /**
     * A named parameter, whatever casing the CLI used for it.
     */
    public function parameter(string $name): mixed
    {
        foreach ($this->parameters as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }
}
