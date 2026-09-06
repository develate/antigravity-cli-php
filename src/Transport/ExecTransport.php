<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Transport;

use Develate\AntigravityCli\Event\EventParser;
use Develate\AntigravityCli\Process\ProcessFactory;
use Develate\AntigravityCli\Process\ProcessResult;

/**
 * One `agy` process per turn.
 *
 * This is the shape a caller wants when each turn is queued independently and
 * may be cancelled on its own: nothing survives the turn except the
 * conversation id, which the next turn passes back with `--conversation=`.
 */
final readonly class ExecTransport implements Transport
{
    /**
     * @param  array<string, string|false>  $env  process environment applied to every run
     */
    public function __construct(
        private string $binary = 'agy',
        private array $env = [],
        private ProcessFactory $processFactory = new ProcessFactory,
        private EventParser $parser = new EventParser,
    ) {
    }

    public function stream(RunRequest $request): \Generator
    {
        $process = $this->processFactory->create(
            $this->command($request),
            $request->cwd(),
            $request->timeout(),
            $this->env,
        );

        $process->start(ArgumentBuilder::inputMessage($request->prompt));

        $lines = $process->lines($request->isCancelled);
        foreach ($lines as $line) {
            foreach ($this->parser->parseLine($line) as $event) {
                yield $event;
            }
        }

        /** @var ProcessResult $result */
        $result = $lines->getReturn();

        return $result;
    }

    /** @return list<string> */
    public function command(RunRequest $request): array
    {
        return (new ArgumentBuilder($this->binary))->stream($request);
    }
}
