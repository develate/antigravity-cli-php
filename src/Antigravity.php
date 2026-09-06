<?php

declare(strict_types=1);

namespace Develate\AntigravityCli;

use Develate\AntigravityCli\Cli\CommandOutput;
use Develate\AntigravityCli\Cli\CommandRunner;
use Develate\AntigravityCli\Cli\Mcp;
use Develate\AntigravityCli\Cli\Plugins;
use Develate\AntigravityCli\Cli\RemoteControl;
use Develate\AntigravityCli\Exception\InvalidOptions;
use Develate\AntigravityCli\Process\AntigravityExecutable;
use Develate\AntigravityCli\Report\PrintReport;
use Develate\AntigravityCli\Report\QuotaReport;
use Develate\AntigravityCli\Report\ReportParser;
use Develate\AntigravityCli\Transport\ExecTransport;
use Develate\AntigravityCli\Transport\PersistentTransport;
use Develate\AntigravityCli\Transport\Transport;
use Develate\AntigravityCli\Value\AgentInfo;
use Develate\AntigravityCli\Value\Capability;
use Develate\AntigravityCli\Value\CapabilitySet;
use Develate\AntigravityCli\Value\ModelInfo;

/**
 * The entry point: an `agy` binary plus the environment it runs under.
 *
 * The environment belongs to the whole object rather than to a single call.
 * Antigravity adopts whatever local identity its configuration directory holds,
 * so a listing and a run that disagreed about that directory would report one
 * account and run as another.
 */
final class Antigravity
{
    private readonly Transport $transport;

    private readonly CommandRunner $runner;

    private ?string $versionCache = null;

    private bool $versionResolved = false;

    private ?string $defaultCwd = null;

    /**
     * @param  array<string, string|false>  $env  merged onto the inherited environment;
     *                                            `false` removes an inherited variable
     */
    public function __construct(
        private readonly string $binary = 'agy',
        ?Transport $transport = null,
        private readonly array $env = [],
        private readonly ?float $timeout = null,
        ?string $version = null,
        ?CommandRunner $runner = null,
    ) {
        $this->transport = $transport ?? new ExecTransport($binary, $env);
        $this->runner = $runner ?? new CommandRunner($binary, $env);

        if ($version !== null) {
            $this->versionCache = $version;
            $this->versionResolved = true;
        }
    }

    /**
     * A copy of this client that runs in `$cwd`.
     */
    public function in(string $cwd): self
    {
        $clone = clone $this;
        $clone->defaultCwd = $cwd;

        return $clone;
    }

    /**
     * A one-shot turn in a fresh conversation.
     */
    public function query(string $prompt, ?SessionOptions $options = null, ?RunOptions $run = null): Result
    {
        return $this->session($options)->query($prompt, $run);
    }

    /**
     * A conversation, one process per turn.
     */
    public function session(?SessionOptions $options = null): Session
    {
        return new Session(
            transport: $this->transport,
            options: $this->resolveOptions($options),
            cliVersion: $this->version(),
            timeout: $this->timeout,
        );
    }

    /**
     * An existing conversation, named explicitly.
     *
     * The id is required. `agy --continue` would resume whatever conversation
     * the CLI used last, which on a shared machine is not necessarily this
     * caller's, so this SDK does not offer it.
     */
    public function resume(string $conversationId, ?SessionOptions $options = null): Session
    {
        if (trim($conversationId) === '') {
            throw new InvalidOptions('Resuming requires a conversation id.');
        }

        return new Session(
            transport: $this->transport,
            options: $this->resolveOptions($options),
            cliVersion: $this->version(),
            timeout: $this->timeout,
            conversationId: $conversationId,
        );
    }

    /**
     * A conversation held open by one long-lived process.
     *
     * Cheaper per turn than `session()` because the CLI starts once, at the
     * cost of a process that lives as long as the session does. Close it when
     * done; the session closes it on destruction either way.
     */
    public function persistentSession(?SessionOptions $options = null): Session
    {
        return new Session(
            transport: new PersistentTransport($this->binary, $this->env),
            options: $this->resolveOptions($options),
            cliVersion: $this->version(),
            timeout: $this->timeout,
        );
    }

    /**
     * The CLI's version, or null when it cannot be read.
     *
     * A binary that will not report its version is not thereby incompatible,
     * so this answers "unknown" rather than failing.
     */
    public function version(): ?string
    {
        if ($this->versionResolved) {
            return $this->versionCache;
        }

        $this->versionResolved = true;

        $output = $this->runner->tryRun(['--version'], 15.0);
        if (!$output->succeeded()) {
            return $this->versionCache = null;
        }

        // Only a recognisable version is reported. Echoing back whatever the
        // binary printed would turn "unknown" into a confident wrong answer.
        return $this->versionCache = preg_match('/([0-9]+(?:\.[0-9]+){1,3}(?:[-+][^\s]+)?)/', $output->stdout, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * Whether this binary exists and answers a command this SDK relies on.
     *
     * Compatibility is decided by the interface being there, not by the version
     * string: an unreadable version is unknown, not incompatible.
     */
    public function isCompatible(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return $this->runner->tryRun(['help', 'models'], 20.0)->succeeded();
    }

    public function isAvailable(): bool
    {
        return AntigravityExecutable::isAvailable($this->binary);
    }

    /**
     * @return list<ModelInfo>
     */
    public function models(): array
    {
        $models = [];

        foreach ($this->runner->run(['models'], 60.0)->lines() as $line) {
            $columns = explode("\t", $line);
            $slug = trim($columns[0] ?? '');
            if ($slug === '') {
                continue;
            }
            $models[] = new ModelInfo($slug, trim($columns[1] ?? '') ?: $slug);
        }

        return $models;
    }

    /**
     * @return list<AgentInfo>
     */
    public function agents(): array
    {
        $agents = [];

        foreach ($this->runner->run(['agents'], 60.0)->lines() as $line) {
            $columns = explode("\t", $line);
            $name = trim($columns[0] ?? '');
            if ($name === '') {
                continue;
            }
            $agents[] = new AgentInfo($name, trim($columns[1] ?? ''));
        }

        return $agents;
    }

    /**
     * The account's remaining quota, as `/usage` reports it.
     *
     * This is a report the CLI answers itself, without asking a model. When it
     * cannot be recognised the result says so and carries the raw text; no
     * percentage or reset time is invented.
     */
    public function quota(?float $timeout = null): QuotaReport
    {
        $report = $this->report('/usage', $timeout);

        return (new ReportParser)->parseQuota($report->text, $report->diagnostics);
    }

    /**
     * A standalone slash-command report.
     *
     * Kept apart from conversation streams: these carry no conversation id and
     * belong to no turn.
     */
    public function report(string $slashCommand, ?float $timeout = null): PrintReport
    {
        $arguments = ['--print='.$slashCommand, '--output-format=text'];
        $output = $this->runner->tryRun($arguments, $timeout ?? 120.0);

        return new PrintReport(
            command: $slashCommand,
            text: $output->stdout,
            diagnostics: $output->stderr,
        );
    }

    public function changelog(?float $timeout = null): CommandOutput
    {
        return $this->runner->run(['changelog'], $timeout ?? 60.0);
    }

    /**
     * Update the CLI in place. Only ever run when a caller asks for it.
     */
    public function update(?float $timeout = null): CommandOutput
    {
        return $this->runner->run(['update'], $timeout ?? 600.0);
    }

    /**
     * Configure shell PATH and aliases. Writes to the user's shell profile.
     */
    public function install(bool $skipPath = false, bool $skipAliases = false, ?string $directory = null): CommandOutput
    {
        $arguments = ['install'];
        if ($skipPath) {
            $arguments[] = '--skip-path';
        }
        if ($skipAliases) {
            $arguments[] = '--skip-aliases';
        }
        if ($directory !== null && $directory !== '') {
            $arguments[] = '--dir='.$directory;
        }

        return $this->runner->run($arguments, 120.0);
    }

    public function mcp(): Mcp
    {
        return new Mcp($this->runner);
    }

    public function plugins(): Plugins
    {
        return new Plugins($this->runner);
    }

    public function remoteControl(): RemoteControl
    {
        return new RemoteControl($this->runner);
    }

    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet;
    }

    public function supports(Capability $capability): bool
    {
        return $this->capabilities()->supports($capability);
    }

    private function resolveOptions(?SessionOptions $options): SessionOptions
    {
        $options ??= new SessionOptions;

        if ($options->cwd !== '') {
            return $options;
        }

        $cwd = $this->defaultCwd ?? getcwd();

        return $options->withCwd(is_string($cwd) ? $cwd : '');
    }
}
