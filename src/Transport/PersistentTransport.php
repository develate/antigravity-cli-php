<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Transport;

use Develate\AntigravityCli\Event\EventParser;
use Develate\AntigravityCli\Event\ResultEvent;
use Develate\AntigravityCli\Process\AntigravityProcess;
use Develate\AntigravityCli\Process\ProcessFactory;
use Develate\AntigravityCli\Process\ProcessResult;

/**
 * One `agy` process shared by every turn of a session.
 *
 * The CLI reads one NDJSON message per line from stdin and answers each with a
 * `result` event, so a turn ends at that event and the process stays up for the
 * next one. Turns are therefore strictly sequential: a second turn started
 * before the first finished would interleave two answers on one pipe with no
 * way to tell them apart.
 */
final class PersistentTransport implements Transport
{
    private ?AntigravityProcess $process = null;

    private bool $busy = false;

    private bool $closed = false;

    /**
     * @param  array<string, string|false>  $env
     */
    public function __construct(
        private readonly string $binary = 'agy',
        private readonly array $env = [],
        private readonly ProcessFactory $processFactory = new ProcessFactory,
        private readonly EventParser $parser = new EventParser,
    ) {
    }

    public function stream(RunRequest $request): \Generator
    {
        if ($this->closed) {
            throw new \LogicException('This session has been closed.');
        }
        if ($this->busy) {
            throw new \LogicException('A session runs one turn at a time; the previous turn is still open.');
        }

        $this->busy = true;

        try {
            $process = $this->ensureStarted($request);
            $process->write(ArgumentBuilder::inputMessage($request->prompt));

            $seenResult = false;

            foreach ($process->streamLines($request->isCancelled) as $line) {
                foreach ($this->parser->parseLine($line) as $event) {
                    yield $event;
                    $seenResult = $seenResult || $event instanceof ResultEvent;
                }
                if ($seenResult) {
                    // The turn is answered. Leaving the loop here abandons the
                    // line generator without stopping the process, which is
                    // what keeps the session available for the next turn.
                    break;
                }
            }

            if (!$seenResult) {
                // The process ended without answering, so the turn has no
                // result and the session cannot be reused.
                $this->close();
            }

            return $process->result();
        } catch (\Throwable $exception) {
            $this->close();

            throw $exception;
        } finally {
            $this->busy = false;
        }
    }

    private function ensureStarted(RunRequest $request): AntigravityProcess
    {
        if ($this->process !== null) {
            return $this->process;
        }

        $process = $this->processFactory->create(
            (new ArgumentBuilder($this->binary))->stream($request),
            $request->cwd(),
            $request->timeout(),
            $this->env,
            keepStdinOpen: true,
        );
        $process->start();

        return $this->process = $process;
    }

    public function isRunning(): bool
    {
        return $this->process?->isRunning() ?? false;
    }

    /**
     * End the process. Idempotent, and safe from a destructor.
     */
    public function close(): void
    {
        $this->closed = true;
        $this->process?->closeInput();
        $this->process?->terminate();
        $this->process = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function result(): ProcessResult
    {
        return $this->process?->result() ?? new ProcessResult(null, '');
    }
}
