<?php

declare(strict_types=1);

namespace Develate\AntigravityCli;

use Develate\AntigravityCli\Event\Event;
use Develate\AntigravityCli\Transport\PersistentTransport;
use Develate\AntigravityCli\Transport\RunMode;
use Develate\AntigravityCli\Transport\RunRequest;
use Develate\AntigravityCli\Transport\Transport;
use Develate\AntigravityCli\Value\RunMetadata;
use Develate\AntigravityCli\Value\Usage;

/**
 * A conversation: turns in order, against one Antigravity conversation id.
 *
 * The id is what makes a session resumable, and it only exists once the CLI has
 * answered: Antigravity allocates it, the SDK never invents one.
 */
final class Session
{
    private ?string $conversationId;

    private ?Result $lastResult = null;

    private ?Usage $usageBaseline = null;

    private RunMode $nextMode;

    private ?Run $currentRun = null;

    /** @var list<callable(Event): void> */
    private array $listeners = [];

    public function __construct(
        private readonly Transport $transport,
        private readonly SessionOptions $options,
        private readonly ?string $cliVersion = null,
        private readonly ?float $timeout = null,
        ?string $conversationId = null,
    ) {
        $this->conversationId = $conversationId === '' ? null : $conversationId;
        $this->nextMode = $this->conversationId === null ? RunMode::Start : RunMode::Resume;
    }

    /**
     * The native conversation id, once one exists.
     */
    public function id(): ?string
    {
        return $this->conversationId;
    }

    public function options(): SessionOptions
    {
        return $this->options;
    }

    /**
     * Run a turn and wait for its result.
     */
    public function query(string $prompt, ?RunOptions $options = null): Result
    {
        return $this->stream($prompt, $options)->result();
    }

    /**
     * Start a turn, iterable as it happens.
     */
    public function stream(string $prompt, ?RunOptions $options = null): Run
    {
        if ($this->currentRun !== null && !$this->currentRun->isSettled()) {
            throw new \LogicException('This session already has a turn in flight; turns run one at a time.');
        }

        $run = $options ?? new RunOptions;
        if ($run->timeout === null && $this->timeout !== null) {
            $run = $run->withTimeout($this->timeout);
        }

        return $this->currentRun = new Run(
            transport: $this->transport,
            request: new RunRequest(
                mode: $this->nextMode,
                conversationId: $this->conversationId,
                prompt: $prompt,
                session: $this->options,
                run: $run,
            ),
            metadata: new RunMetadata($this->cliVersion, $this->options->model, $this->options->cwd),
            listeners: $this->listeners,
            onComplete: function (Result $result): void {
                $this->lastResult = $result;
                if ($result->conversationId !== '') {
                    $this->conversationId = $result->conversationId;
                    $this->nextMode = RunMode::Resume;
                }
                // Next turn's baseline, so a cumulative total can be turned into
                // that turn's own consumption if its steps report none.
                $this->usageBaseline = $result->cumulativeUsage ?? $this->usageBaseline;
            },
            usageBaseline: $this->usageBaseline,
        );
    }

    public function result(): Result
    {
        return $this->lastResult ?? throw new \LogicException('This session has no completed turn yet.');
    }

    /**
     * The conversation's cumulative usage as the CLI last reported it.
     */
    public function cumulativeUsage(): ?Usage
    {
        return $this->lastResult?->cumulativeUsage;
    }

    /** @param callable(Event): void $listener */
    public function onEvent(callable $listener): self
    {
        $this->listeners[] = $listener;

        return $this;
    }

    /**
     * Release the session's process, if it holds one.
     */
    public function close(): void
    {
        if ($this->transport instanceof PersistentTransport) {
            $this->transport->close();
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
