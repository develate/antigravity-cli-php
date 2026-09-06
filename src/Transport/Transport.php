<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Transport;

use Develate\AntigravityCli\Event\Event;
use Develate\AntigravityCli\Process\ProcessResult;

interface Transport
{
    /**
     * One turn's events.
     *
     * @return \Generator<int, Event, void, ProcessResult>
     */
    public function stream(RunRequest $request): \Generator;
}
