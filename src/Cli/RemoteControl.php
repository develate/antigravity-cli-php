<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Cli;

/**
 * `agy remote-control`, the background daemon that exposes this machine's CLI.
 */
final readonly class RemoteControl
{
    public function __construct(private CommandRunner $runner)
    {
    }

    public function start(): CommandOutput
    {
        return $this->runner->run(['remote-control', 'start'], 120.0);
    }

    /**
     * The daemon's status, reported rather than thrown: "not running" is a
     * status, and the command exits non-zero to say so.
     */
    public function status(): CommandOutput
    {
        return $this->runner->tryRun(['remote-control', 'status'], 30.0);
    }

    public function stop(): CommandOutput
    {
        return $this->runner->run(['remote-control', 'stop'], 60.0);
    }
}
