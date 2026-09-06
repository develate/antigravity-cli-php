<?php

declare(strict_types=1);

namespace Develate\AntigravityCli;

use Develate\AntigravityCli\Event\Event;
use Develate\AntigravityCli\Exception\ProcessFailed;
use Develate\AntigravityCli\Exception\RunFailed;
use Develate\AntigravityCli\Process\ProcessResult;
use Develate\AntigravityCli\Transport\RunRequest;
use Develate\AntigravityCli\Transport\Transport;
use Develate\AntigravityCli\Value\RunMetadata;
use Develate\AntigravityCli\Value\Usage;

/**
 * One turn, iterable as it happens.
 *
 * @implements \IteratorAggregate<int, Event>
 */
final class Run implements \IteratorAggregate
{
    private bool $cancelled = false;

    private bool $iterating = false;

    private bool $completed = false;

    private ?Result $result = null;

    private ?\Generator $execution = null;

    private ?\Throwable $failure = null;

    /** @var list<Event> */
    private array $events = [];

    /**
     * @param  list<callable(Event): void>  $listeners
     * @param  \Closure(Result): void  $onComplete
     */
    public function __construct(
        private readonly Transport $transport,
        private readonly RunRequest $request,
        private readonly RunMetadata $metadata,
        private readonly array $listeners = [],
        private readonly ?\Closure $onComplete = null,
        private readonly ?Usage $usageBaseline = null,
    ) {
    }

    public function getIterator(): \Traversable
    {
        if ($this->completed) {
            yield from $this->events;

            return;
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->iterating) {
            throw new \LogicException('A run cannot be iterated concurrently.');
        }

        $this->execution ??= $this->execute();
        $this->iterating = true;

        try {
            while ($this->execution->valid()) {
                yield $this->execution->current();
                $this->execution->next();
            }
        } finally {
            $this->iterating = false;
        }
    }

    /**
     * Drive the turn to completion and return its result.
     */
    public function result(): Result
    {
        if (!$this->completed) {
            foreach ($this as $ignored) {
                // Drain: the result is assembled as the events go by.
            }
        }

        return $this->result ?? throw $this->failure ?? new \LogicException('The run did not produce a result.');
    }

    /**
     * Ask the running process to stop at the next opportunity.
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function isComplete(): bool
    {
        return $this->completed;
    }

    /**
     * Whether this run is over, successfully or not.
     */
    public function isSettled(): bool
    {
        return $this->completed || $this->failure !== null;
    }

    /** @return list<Event> */
    public function events(): array
    {
        return $this->events;
    }

    /** @return \Generator<int, Event, void, void> */
    private function execute(): \Generator
    {
        $builder = new ResultBuilder($this->request->conversationId, $this->usageBaseline);

        try {
            $stream = $this->transport->stream(
                $this->request->withCancellation(fn (): bool => $this->cancelled),
            );

            foreach ($stream as $event) {
                $builder->add($event);
                $this->events[] = $event;
                foreach ($this->listeners as $listener) {
                    $listener($event);
                }
                yield $event;
            }

            /** @var ProcessResult $process */
            $process = $stream->getReturn();

            // The CLI reports an unsuccessful turn in the result event and can
            // still exit 0, so neither signal alone decides the outcome.
            if (!$builder->hasResult()) {
                throw new ProcessFailed($process->exitCode ?? 1, $process->stderr);
            }

            $this->result = $builder->build($this->metadata, $process->exitCode);
            $this->completed = true;

            if ($this->onComplete !== null) {
                ($this->onComplete)($this->result);
            }
        } catch (\Throwable $exception) {
            $this->failure = $exception;

            throw $exception;
        }
    }

    /**
     * The result, or an exception when the turn did not succeed.
     */
    public function resultOrFail(): Result
    {
        $result = $this->result();

        if (!$result->isSuccess()) {
            throw new RunFailed($result->status->value, $result->error ?? '');
        }

        return $result;
    }
}
