<?php

declare(strict_types=1);

namespace Develate\AntigravityCli;

use Develate\AntigravityCli\Event\CommandResultEvent;
use Develate\AntigravityCli\Event\Event;
use Develate\AntigravityCli\Event\InitEvent;
use Develate\AntigravityCli\Tool\Command;
use Develate\AntigravityCli\Tool\FileChange;
use Develate\AntigravityCli\Tool\ToolCall;
use Develate\AntigravityCli\Value\DeniedAction;
use Develate\AntigravityCli\Value\FileChangeKind;
use Develate\AntigravityCli\Value\ResultStatus;
use Develate\AntigravityCli\Value\RunMetadata;
use Develate\AntigravityCli\Value\Usage;

final readonly class Result
{
    /**
     * @param  Usage|null  $usage  this turn's consumption; null when it cannot be known
     * @param  Usage|null  $cumulativeUsage  the conversation's total as the CLI reported it
     * @param  list<DeniedAction>  $deniedActions
     * @param  list<ToolCall>  $toolCalls
     * @param  list<Event>  $events
     * @param  list<CommandResultEvent>  $reports
     */
    public function __construct(
        public string $conversationId,
        public string $text,
        public ResultStatus $status,
        public ?string $error,
        public ?Usage $usage,
        public ?Usage $cumulativeUsage,
        public int $numTurns,
        public ?float $durationSeconds,
        public array $deniedActions,
        public array $toolCalls,
        public array $events,
        public array $reports,
        public ?InitEvent $init,
        public RunMetadata $metadata,
        public ?int $exitCode,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status->isSuccess();
    }

    /**
     * Whether the CLI's permission rules refused something during the turn.
     *
     * A sandboxed run reports refusals here rather than prompting, so a turn can
     * succeed having quietly skipped work the caller asked for.
     */
    public function hasDenials(): bool
    {
        return $this->deniedActions !== [];
    }

    /** @return list<Command> */
    public function commands(): array
    {
        $commands = [];
        foreach ($this->toolCalls as $call) {
            if (strcasecmp($call->name, 'run_command') !== 0) {
                continue;
            }
            $line = $call->parameter('CommandLine');
            $commands[] = new Command(
                is_string($line) ? $line : '',
                $call->failed(),
                $call->error?->message,
            );
        }

        return $commands;
    }

    /** @return list<FileChange> */
    public function fileChanges(): array
    {
        $changes = [];
        foreach ($this->toolCalls as $call) {
            $kind = match (strtolower($call->name)) {
                'write_to_file' => FileChangeKind::Write,
                'replace_file_content', 'multi_replace_file_content', 'sed_file', 'notebook_edit' => FileChangeKind::Edit,
                default => null,
            };
            if ($kind === null) {
                continue;
            }
            $path = $call->parameter('TargetFile') ?? $call->parameter('AbsolutePath') ?? $call->parameter('file_path');
            if (is_string($path) && $path !== '') {
                $changes[] = new FileChange($path, $kind, $call->failed());
            }
        }

        return $changes;
    }

    /** @return list<ToolCall> */
    public function toolCalls(): array
    {
        return $this->toolCalls;
    }

    /** @return list<Event> */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * What the run resolved at startup: the model behind the flag, the tools it
     * was given, the permission mode it settled on.
     */
    public function init(): ?InitEvent
    {
        return $this->init;
    }
}
