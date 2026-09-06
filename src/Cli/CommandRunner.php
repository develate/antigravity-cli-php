<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Cli;

use Develate\AntigravityCli\Exception\AntigravityNotFound;
use Develate\AntigravityCli\Exception\ProcessFailed;
use Develate\AntigravityCli\Process\AntigravityExecutable;
use Symfony\Component\Process\Exception\ProcessFailedException as SymfonyProcessFailed;
use Symfony\Component\Process\Exception\RuntimeException as SymfonyRuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs a plain `agy` subcommand and hands back what it printed.
 *
 * Antigravity writes routine diagnostics to stderr on every invocation, so
 * stderr is returned alongside stdout rather than being read as a failure.
 */
class CommandRunner
{
    /**
     * @param  array<string, string|false>  $env
     */
    public function __construct(
        private readonly string $binary = 'agy',
        private readonly array $env = [],
        private readonly ?string $cwd = null,
        private readonly float $defaultTimeout = 60.0,
    ) {
    }

    /**
     * @param  list<string>  $arguments
     *
     * @throws ProcessFailed when the command exits non-zero
     */
    public function run(array $arguments, ?float $timeout = null): CommandOutput
    {
        $output = $this->tryRun($arguments, $timeout);

        if ($output->exitCode !== 0) {
            throw new ProcessFailed(
                $output->exitCode,
                $output->stdout !== '' ? $output->stdout : $output->stderr,
            );
        }

        return $output;
    }

    /**
     * The same, but a non-zero exit is returned instead of thrown.
     *
     * Several `agy` commands answer a question by failing, and "no" is an
     * answer rather than an error.
     *
     * @param  list<string>  $arguments
     */
    public function tryRun(array $arguments, ?float $timeout = null): CommandOutput
    {
        AntigravityExecutable::assertAvailable($this->binary);

        $process = new Process(
            [$this->binary, ...$arguments],
            $this->cwd,
            $this->env,
            null,
            $timeout ?? $this->defaultTimeout,
        );

        try {
            $process->run();
        } catch (SymfonyProcessFailed $exception) {
            $failed = $exception->getProcess();

            return new CommandOutput(
                $failed->getExitCode() ?? 1,
                $failed->getOutput(),
                $failed->getErrorOutput(),
            );
        } catch (SymfonyRuntimeException $exception) {
            throw new AntigravityNotFound(
                sprintf('Antigravity binary "%s" could not be started.', $this->binary),
                0,
                $exception,
            );
        }

        return new CommandOutput(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
