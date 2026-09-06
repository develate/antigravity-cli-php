<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Event;

use Develate\AntigravityCli\Value\StepState;
use Develate\AntigravityCli\Value\StepType;
use Develate\AntigravityCli\Value\ToolError;
use Develate\AntigravityCli\Value\Usage;

/**
 * One update to one step of the conversation.
 *
 * A step is identified by conversation and index, and is reported more than
 * once as it moves from `ACTIVE` to a terminal state. Consumers that merge on
 * that identity see progress; consumers that append see the same step twice.
 */
final readonly class StepUpdateEvent implements Event
{
    /**
     * @param array<string, mixed>|null $toolParameters
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $conversationId,
        public int $stepIndex,
        public StepState $state,
        public StepType $stepType,
        public ?string $textDelta,
        public ?string $toolName,
        public ?array $toolParameters,
        public ?ToolError $toolError,
        public ?float $durationSeconds,
        public ?Usage $usage,
        private array $raw,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $step = $payload['step_update'] ?? [];
        $step = is_array($step) ? $step : [];
        $info = is_array($step['tool_info'] ?? null) ? $step['tool_info'] : [];
        $parameters = is_array($info['parameters'] ?? null) ? $info['parameters'] : null;
        $usage = is_array($step['usage'] ?? null) ? $step['usage'] : null;
        $duration = $step['duration_seconds'] ?? null;
        $index = $step['step_index'] ?? null;
        $textDelta = $step['text_delta'] ?? null;
        $toolName = $step['tool_name'] ?? null;

        return new self(
            conversationId: is_string($step['conversation_id'] ?? null) ? $step['conversation_id'] : '',
            stepIndex: is_numeric($index) ? (int) $index : -1,
            state: StepState::parse(is_string($step['state'] ?? null) ? $step['state'] : null),
            stepType: StepType::parse(is_string($step['step_type'] ?? null) ? $step['step_type'] : null),
            textDelta: is_string($textDelta) ? $textDelta : null,
            toolName: is_string($toolName) && $toolName !== '' ? $toolName : null,
            toolParameters: $parameters,
            toolError: ToolError::fromArray(is_array($info['error'] ?? null) ? $info['error'] : null),
            durationSeconds: is_numeric($duration) ? (float) $duration : null,
            usage: $usage === null ? null : Usage::fromArray($usage),
            raw: $payload,
        );
    }

    /**
     * A stable key for the step this update belongs to.
     */
    public function stepKey(): string
    {
        return $this->conversationId.'#'.$this->stepIndex;
    }

    public function isTerminal(): bool
    {
        return $this->state->isTerminal();
    }

    public function name(): string
    {
        return 'step_update';
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
