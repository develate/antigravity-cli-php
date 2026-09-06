<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Support;

use Develate\AntigravityCli\Event\EventParser;
use Develate\AntigravityCli\Process\ProcessResult;
use Develate\AntigravityCli\Transport\RunRequest;
use Develate\AntigravityCli\Transport\Transport;

/**
 * A transport that replays canned NDJSON, one script per turn.
 */
final class ScriptedTransport implements Transport
{
    /** @var list<RunRequest> */
    public array $requests = [];

    /** @var list<list<string>> */
    private array $turns;

    private int $turn = 0;

    /**
     * @param  list<list<string>>  $turns  NDJSON lines per turn
     */
    public function __construct(array $turns, private readonly ?int $exitCode = 0)
    {
        $this->turns = $turns;
    }

    public function stream(RunRequest $request): \Generator
    {
        $this->requests[] = $request;
        $lines = $this->turns[$this->turn] ?? [];
        $this->turn++;

        $parser = new EventParser;

        foreach ($lines as $line) {
            if ($request->isCancelled !== null && ($request->isCancelled)()) {
                throw new \Develate\AntigravityCli\Exception\ProcessCancelled('cancelled');
            }
            foreach ($parser->parseLine($line) as $event) {
                yield $event;
            }
        }

        return new ProcessResult($this->exitCode, '');
    }
}
