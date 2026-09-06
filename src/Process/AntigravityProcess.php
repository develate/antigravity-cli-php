<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Process;

use Develate\AntigravityCli\Exception\ProcessCancelled;
use Develate\AntigravityCli\Exception\ProcessTimedOut;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOut;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * One `agy` process, read as a stream of NDJSON lines.
 *
 * Antigravity writes a great deal of diagnostic noise to stderr even on
 * successful runs, so stderr is collected for reporting but never treated as
 * failure on its own.
 */
final class AntigravityProcess
{
    private ?Process $process = null;

    private ?InputStream $input = null;

    private string $stderr = '';

    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env  merged onto the inherited environment;
     *                                            `false` removes an inherited variable
     */
    public function __construct(
        private readonly array $command,
        private readonly ?string $cwd = null,
        private readonly ?float $timeout = null,
        private readonly array $env = [],
        private readonly bool $keepStdinOpen = false,
    ) {
    }

    /**
     * Start the process. Callers that stream several turns over one process do
     * this once and then push each turn's input.
     */
    public function start(?string $input = null): void
    {
        if ($this->process !== null) {
            throw new \LogicException('This process has already been started.');
        }

        AntigravityExecutable::assertAvailable($this->command[0]);

        if ($this->keepStdinOpen) {
            $this->input = new InputStream;
            $stdin = $this->input;
        } else {
            $stdin = $input;
        }

        $this->process = new Process($this->command, $this->cwd, $this->env, $stdin, $this->timeout);
        $this->process->start();

        if ($this->keepStdinOpen && $input !== null && $input !== '') {
            $this->write($input);
        }
    }

    public function write(string $input): void
    {
        if ($this->input === null) {
            throw new \LogicException('This process was not started with an open stdin.');
        }

        $this->input->write($input);
    }

    public function closeInput(): void
    {
        $this->input?->close();
    }

    public function isRunning(): bool
    {
        return $this->process?->isRunning() ?? false;
    }

    public function stderr(): string
    {
        return $this->stderr;
    }

    public function exitCode(): ?int
    {
        return $this->process?->getExitCode();
    }

    /**
     * Every NDJSON line until the process ends.
     *
     * @return \Generator<int, string, void, ProcessResult>
     */
    public function lines(?\Closure $isCancelled = null): \Generator
    {
        if ($this->process === null) {
            $this->start();
        }

        $reader = new NdjsonReader;

        try {
            yield from $this->pump($reader, $isCancelled);

            foreach ($reader->push($this->process->getIncrementalOutput()) as $line) {
                yield $line;
            }
            $this->drainStderr();

            if (($last = $reader->finish()) !== null) {
                yield $last;
            }
        } finally {
            $this->terminate();
        }

        return $this->result();
    }

    /**
     * Lines until the process ends, leaving it alive for the caller to reuse.
     *
     * Unlike `lines()` this does not stop the process when the generator is
     * abandoned: a session breaks out of the loop as soon as a turn's result
     * arrives and then sends the next turn down the same pipe. Failures still
     * stop it, because a process that threw is not reusable.
     *
     * @return \Generator<int, string, void, void>
     */
    public function streamLines(?\Closure $isCancelled = null): \Generator
    {
        if ($this->process === null) {
            throw new \LogicException('This process has not been started.');
        }

        $reader = new NdjsonReader;

        try {
            yield from $this->pump($reader, $isCancelled);

            foreach ($reader->push($this->process->getIncrementalOutput()) as $line) {
                yield $line;
            }
            $this->drainStderr();

            if (($last = $reader->finish()) !== null) {
                yield $last;
            }
        } catch (\Throwable $exception) {
            $this->terminate();

            throw $exception;
        }
    }

    /**
     * The polling loop shared by both read modes.
     *
     * @return \Generator<int, string, void, void>
     */
    private function pump(NdjsonReader $reader, ?\Closure $isCancelled): \Generator
    {
        \assert($this->process !== null);

        try {
            while ($this->process->isRunning()) {
                if ($isCancelled !== null && $isCancelled()) {
                    $this->terminate();

                    throw new ProcessCancelled('Antigravity run was cancelled.');
                }

                $this->process->checkTimeout();

                foreach ($reader->push($this->process->getIncrementalOutput()) as $line) {
                    yield $line;
                }
                $this->drainStderr();

                if ($this->process->isRunning()) {
                    usleep(10_000);
                }
            }
        } catch (SymfonyProcessTimedOut $exception) {
            $this->terminate();

            throw new ProcessTimedOut($exception->getMessage(), 0, $exception);
        }
    }

    public function result(): ProcessResult
    {
        return new ProcessResult($this->process?->getExitCode(), $this->stderr);
    }

    /**
     * Stop the process and release its handles.
     *
     * Safe to call repeatedly: cancellation, an exception and an abandoned
     * iteration all end up here, and none of them may leave a child behind.
     */
    public function terminate(): void
    {
        if ($this->process === null) {
            return;
        }

        $this->drainStderr();
        $this->input?->close();

        if ($this->process->isRunning()) {
            $this->process->stop(0.2);
        }
    }

    private function drainStderr(): void
    {
        if ($this->process !== null) {
            $this->stderr .= $this->process->getIncrementalErrorOutput();
        }
    }
}
