<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Process;

final readonly class ProcessResult
{
    /**
     * @param  int|null  $exitCode  null while the process is still running, as it
     *                              is for a session that answered a turn and is
     *                              waiting for the next one
     */
    public function __construct(public ?int $exitCode, public string $stderr)
    {
    }
}
