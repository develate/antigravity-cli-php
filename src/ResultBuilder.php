<?php

declare(strict_types=1);

namespace Develate\AntigravityCli;

use Develate\AntigravityCli\Event\CommandResultEvent;
use Develate\AntigravityCli\Event\Event;
use Develate\AntigravityCli\Event\InitEvent;
use Develate\AntigravityCli\Event\ResultEvent;
use Develate\AntigravityCli\Event\StepUpdateEvent;
use Develate\AntigravityCli\Tool\ToolCall;
use Develate\AntigravityCli\Value\ResultStatus;
use Develate\AntigravityCli\Value\RunMetadata;
use Develate\AntigravityCli\Value\StepType;
use Develate\AntigravityCli\Value\Usage;

/**
 * Folds one turn's events into a `Result`.
 *
 * Two things here are not obvious. Steps arrive repeatedly as they progress, so
 * they are merged on their index instead of appended. And the `result` event's
 * usage counts the whole conversation, so the turn's own usage is summed from
 * the steps that completed during this turn; the cumulative figure is only used
 * when a baseline from the previous turn makes the difference meaningful.
 */
final class ResultBuilder
{
    private ?InitEvent $init = null;

    private ?ResultEvent $result = null;

    /** @var list<Event> */
    private array $events = [];

    /** @var array<string, ToolCall> */
    private array $toolCalls = [];

    /** @var array<string, string> */
    private array $stepText = [];

    /** @var array<string, Usage> */
    private array $stepUsage = [];

    /** @var list<CommandResultEvent> */
    private array $reports = [];

    private ?string $conversationId;

    public function __construct(?string $conversationId = null, private readonly ?Usage $usageBaseline = null)
    {
        $this->conversationId = $conversationId === '' ? null : $conversationId;
    }

    public function add(Event $event): void
    {
        $this->events[] = $event;

        match (true) {
            $event instanceof InitEvent => $this->addInit($event),
            $event instanceof StepUpdateEvent => $this->addStep($event),
            $event instanceof ResultEvent => $this->addResult($event),
            $event instanceof CommandResultEvent => $this->reports[] = $event,
            default => null,
        };
    }

    public function hasResult(): bool
    {
        return $this->result !== null;
    }

    public function conversationId(): ?string
    {
        return $this->conversationId;
    }

    /**
     * The conversation's cumulative usage as last reported, for use as the next
     * turn's baseline.
     */
    public function cumulativeUsage(): ?Usage
    {
        return $this->result?->cumulativeUsage;
    }

    private function addInit(InitEvent $event): void
    {
        $this->init = $event;
        if ($event->conversationId !== '') {
            $this->conversationId = $event->conversationId;
        }
    }

    private function addResult(ResultEvent $event): void
    {
        $this->result = $event;
        if ($event->conversationId !== '') {
            $this->conversationId = $event->conversationId;
        }
    }

    private function addStep(StepUpdateEvent $event): void
    {
        if ($event->conversationId !== '') {
            $this->conversationId ??= $event->conversationId;
        }

        $key = $event->stepKey();

        if ($event->stepType === StepType::Tool && $event->toolName !== null) {
            $call = $this->toolCalls[$key] ??= new ToolCall(
                $event->conversationId,
                $event->stepIndex,
                $event->toolName,
            );
            if ($event->toolParameters !== null) {
                $call->parameters = $event->toolParameters;
            }
            $call->state = $event->state;
            $call->error = $event->toolError ?? $call->error;
            $call->durationSeconds = $event->durationSeconds ?? $call->durationSeconds;
        }

        if ($event->stepType === StepType::AgentResponse && $event->textDelta !== null) {
            $this->stepText[$key] = ($this->stepText[$key] ?? '').$event->textDelta;
        }

        // Usage is reported once per step, on the update that completes it.
        if ($event->usage !== null && $event->state->isTerminal()) {
            $this->stepUsage[$key] = $event->usage;
        }
    }

    /**
     * This turn's usage, summed over the steps that completed during it.
     *
     * Falls back to the difference against a known baseline when no step
     * reported usage, and to unknown when neither is available.
     */
    public function turnUsage(): ?Usage
    {
        if ($this->stepUsage !== []) {
            $total = Usage::zero();
            foreach ($this->stepUsage as $usage) {
                $total = $total->plus($usage);
            }

            return $total;
        }

        $cumulative = $this->result?->cumulativeUsage;
        if ($cumulative === null || $cumulative->isEmpty()) {
            return null;
        }

        if ($this->usageBaseline !== null) {
            return $cumulative->minus($this->usageBaseline);
        }

        // Without a baseline the cumulative figure is only this turn's when the
        // conversation had no earlier turn.
        return $this->result?->numTurns === 1 ? $cumulative : null;
    }

    /**
     * The turn's assistant text.
     *
     * The `result` event carries the finished response, so the accumulated
     * deltas are only used when it did not arrive.
     */
    public function text(): string
    {
        $response = $this->result?->response ?? '';
        if ($response !== '') {
            return $response;
        }

        return implode('', $this->stepText);
    }

    public function build(RunMetadata $metadata, ?int $exitCode): Result
    {
        return new Result(
            conversationId: $this->conversationId ?? '',
            text: $this->text(),
            status: $this->result?->status ?? ResultStatus::Unknown,
            error: $this->result?->error,
            usage: $this->turnUsage(),
            cumulativeUsage: $this->result?->cumulativeUsage,
            numTurns: $this->result?->numTurns ?? 0,
            durationSeconds: $this->result?->durationSeconds,
            deniedActions: $this->result?->deniedActions ?? [],
            toolCalls: array_values($this->toolCalls),
            events: $this->events,
            reports: $this->reports,
            init: $this->init,
            metadata: $metadata,
            exitCode: $exitCode,
        );
    }
}
