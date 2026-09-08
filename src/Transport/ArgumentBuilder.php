<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Transport;

use Develate\AntigravityCli\Exception\InvalidOptions;
use Develate\AntigravityCli\RunOptions;
use Develate\AntigravityCli\SessionOptions;
use Develate\AntigravityCli\Value\ModelCapabilities;

/**
 * Builds the `agy` command line.
 *
 * Every flag is written in the attached `--flag=value` form. `agy --print`
 * takes the following argument as its own value, so a detached `--print` in
 * front of another flag consumes that flag and the CLI then complains about a
 * prompt it never received. Attaching the value avoids that for every flag
 * rather than only the ones known to be affected today.
 */
final readonly class ArgumentBuilder
{
    public function __construct(private string $binary = 'agy')
    {
    }

    /**
     * The command for a turn streamed over stdin.
     *
     * The prompt travels as an NDJSON message on stdin rather than in
     * `--print=`, which keeps it clear of the operating system's argument
     * length limit and is the same path a multi-turn session uses.
     *
     * @return list<string>
     */
    public function stream(RunRequest $request): array
    {
        $command = [
            $this->binary,
            '--print=',
            '--input-format=stream-json',
            '--output-format=stream-json',
        ];

        if ($request->mode === RunMode::Resume) {
            $id = $request->conversationId;
            if ($id === null || $id === '') {
                throw new InvalidOptions('Resuming requires an explicit conversation id.');
            }
            $command[] = '--conversation='.$id;
        }

        array_push($command, ...$this->sessionFlags($request->session));
        array_push($command, ...$this->runFlags($request->run));

        return $command;
    }

    /**
     * The command for a standalone report such as `/usage`.
     *
     * @return list<string>
     */
    public function report(string $slashCommand, ?float $printTimeout = null): array
    {
        if (!str_starts_with($slashCommand, '/')) {
            throw new InvalidOptions('A report command must start with "/".');
        }

        $command = [
            $this->binary,
            '--print='.$slashCommand,
            '--output-format=stream-json',
        ];

        if ($printTimeout !== null) {
            $command[] = '--print-timeout='.self::duration($printTimeout);
        }

        return $command;
    }

    /**
     * A plain subcommand such as `models` or `mcp list`.
     *
     * @param  list<string>  $arguments
     * @return list<string>
     */
    public function subcommand(array $arguments): array
    {
        return [$this->binary, ...$arguments];
    }

    /** @return list<string> */
    private function sessionFlags(SessionOptions $session): array
    {
        $flags = [];

        if ($session->model !== null && $session->model !== '') {
            $flags[] = '--model='.$session->model;
        }
        if ($session->effort !== null && ModelCapabilities::supportsReasoningEffort($session->model)) {
            $flags[] = '--effort='.$session->effort->value;
        }
        if ($session->agent !== null) {
            $flags[] = '--agent='.$session->agent;
        }
        if ($session->mode !== null) {
            $flags[] = '--mode='.$session->mode->value;
        }
        if ($session->sandbox) {
            $flags[] = '--sandbox';
        }
        if ($session->dangerouslyBypassPermissions) {
            $flags[] = '--dangerously-skip-permissions';
        }
        foreach ($session->additionalDirectories as $directory) {
            $flags[] = '--add-dir='.$directory;
        }
        if ($session->project !== null) {
            $flags[] = '--project='.$session->project;
        }
        if ($session->newProject) {
            $flags[] = '--new-project';
        }
        if ($session->disableSlashCommands) {
            $flags[] = '--disable-slash-commands';
        }
        if ($session->logFile !== null && $session->logFile !== '') {
            $flags[] = '--log-file='.$session->logFile;
        }

        return $flags;
    }

    /** @return list<string> */
    private function runFlags(RunOptions $run): array
    {
        $flags = [];

        if ($run->printTimeout !== null) {
            $flags[] = '--print-timeout='.self::duration($run->printTimeout);
        }
        if ($run->schema !== null) {
            $flags[] = '--json-schema='.(is_string($run->schema)
                ? $run->schema
                : json_encode($run->schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }

        return $flags;
    }

    /**
     * Seconds, in the Go duration syntax `--print-timeout` parses.
     */
    private static function duration(float $seconds): string
    {
        return rtrim(rtrim(sprintf('%.3F', $seconds), '0'), '.').'s';
    }

    /**
     * One NDJSON turn, as it is written to the child process' stdin.
     */
    public static function inputMessage(string $prompt): string
    {
        return json_encode(
            ['event' => 'user', 'message' => ['content' => $prompt]],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";
    }
}
