<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Event;

use Develate\AntigravityCli\Value\DeniedAction;
use Develate\AntigravityCli\Value\ResultStatus;
use Develate\AntigravityCli\Value\Usage;

/**
 * The end of one turn.
 *
 * `usage` here is cumulative for the whole conversation, not the turn: a resumed
 * conversation reports the sum of every turn so far. Treating it as the turn's
 * consumption double-counts every earlier turn, so callers wanting a per-turn
 * figure should read `Result::usage`, which is built from the turn's own steps.
 */
final readonly class ResultEvent implements Event
{
    /**
     * @param list<DeniedAction> $deniedActions
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $conversationId,
        public ResultStatus $status,
        public string $response,
        public ?string $error,
        public ?float $durationSeconds,
        public int $numTurns,
        public Usage $cumulativeUsage,
        public array $deniedActions,
        private array $raw,
        public ?string $finishedReason = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $result = $payload['result'] ?? [];
        $result = is_array($result) ? $result : [];

        $denied = [];
        foreach (is_array($result['denied_actions'] ?? null) ? $result['denied_actions'] : [] as $action) {
            if (is_array($action)) {
                $denied[] = DeniedAction::fromArray($action);
            }
        }

        $duration = $result['duration_seconds'] ?? null;
        $turns = $result['num_turns'] ?? null;
        $error = $result['error'] ?? null;
        $response = $result['response'] ?? '';

        return new self(
            conversationId: is_string($result['conversation_id'] ?? null) ? $result['conversation_id'] : '',
            status: ResultStatus::parse(is_string($result['status'] ?? null) ? $result['status'] : null),
            response: is_string($response) ? $response : '',
            error: is_string($error) && $error !== '' ? $error : null,
            durationSeconds: is_numeric($duration) ? (float) $duration : null,
            numTurns: is_numeric($turns) ? (int) $turns : 0,
            cumulativeUsage: Usage::fromArray(is_array($result['usage'] ?? null) ? $result['usage'] : null),
            deniedActions: $denied,
            raw: $payload,
            finishedReason: is_string($result['finished_reason'] ?? null) ? $result['finished_reason'] : null,
        );
    }

    public function name(): string
    {
        return 'result';
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
