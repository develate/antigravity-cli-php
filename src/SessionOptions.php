<?php

declare(strict_types=1);

namespace Develate\AntigravityCli;

use Develate\AntigravityCli\Exception\InvalidOptions;
use Develate\AntigravityCli\Value\Effort;
use Develate\AntigravityCli\Value\ExecutionMode;

/**
 * What stays the same for every turn of a conversation.
 */
final readonly class SessionOptions
{
    /**
     * @param  list<string>  $additionalDirectories  extra workspace roots (`--add-dir`)
     * @param  ExecutionMode|null  $mode  null inherits CLI settings/defaults (Auto where available)
     * @param  bool  $sandbox  request --sandbox; does not attest active host containment
     * @param  bool  $dangerouslyBypassPermissions  skip ordinary approvals; protected-command and administrator restrictions still apply
     */
    public function __construct(
        public string $cwd = '',
        public ?string $model = null,
        public ?Effort $effort = null,
        public ?string $agent = null,
        public ?ExecutionMode $mode = null,
        public bool $sandbox = false,
        public bool $dangerouslyBypassPermissions = false,
        public array $additionalDirectories = [],
        public ?string $project = null,
        public bool $newProject = false,
        public bool $disableSlashCommands = false,
        public ?string $logFile = null,
    ) {
        $this->validate();
    }

    /**
     * Combinations the CLI cannot honour, rejected before a process is started.
     */
    private function validate(): void
    {
        if ($this->dangerouslyBypassPermissions && $this->sandbox) {
            throw new InvalidOptions(
                'The sandbox and permission bypass cannot be combined: bypassing permissions is what the sandbox exists to prevent.',
            );
        }

        if ($this->project !== null && $this->newProject) {
            throw new InvalidOptions('A run cannot both join project "'.$this->project.'" and create a new one.');
        }

        if ($this->project !== null && $this->project === '') {
            throw new InvalidOptions('The project must not be empty.');
        }

        if ($this->agent !== null && $this->agent === '') {
            throw new InvalidOptions('The agent must not be empty.');
        }
    }

    public function withCwd(string $cwd): self
    {
        return $this->copy(['cwd' => $cwd]);
    }

    public function withModel(?string $model): self
    {
        return $this->copy(['model' => $model]);
    }

    public function withEffort(?Effort $effort): self
    {
        return $this->copy(['effort' => $effort]);
    }

    public function withAgent(?string $agent): self
    {
        return $this->copy(['agent' => $agent]);
    }

    public function withMode(?ExecutionMode $mode): self
    {
        return $this->copy(['mode' => $mode]);
    }

    public function withSandbox(bool $sandbox = true): self
    {
        return $this->copy(['sandbox' => $sandbox]);
    }

    public function withBypassedPermissions(bool $bypass = true): self
    {
        return $this->copy(['dangerouslyBypassPermissions' => $bypass]);
    }

    /** @param list<string> $directories */
    public function withAdditionalDirectories(array $directories): self
    {
        return $this->copy(['additionalDirectories' => $directories]);
    }

    public function withProject(?string $project): self
    {
        return $this->copy(['project' => $project]);
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(
            cwd: $changes['cwd'] ?? $this->cwd,
            model: array_key_exists('model', $changes) ? $changes['model'] : $this->model,
            effort: array_key_exists('effort', $changes) ? $changes['effort'] : $this->effort,
            agent: array_key_exists('agent', $changes) ? $changes['agent'] : $this->agent,
            mode: array_key_exists('mode', $changes) ? $changes['mode'] : $this->mode,
            sandbox: $changes['sandbox'] ?? $this->sandbox,
            dangerouslyBypassPermissions: $changes['dangerouslyBypassPermissions'] ?? $this->dangerouslyBypassPermissions,
            additionalDirectories: $changes['additionalDirectories'] ?? $this->additionalDirectories,
            project: array_key_exists('project', $changes) ? $changes['project'] : $this->project,
            newProject: $changes['newProject'] ?? $this->newProject,
            disableSlashCommands: $changes['disableSlashCommands'] ?? $this->disableSlashCommands,
            logFile: array_key_exists('logFile', $changes) ? $changes['logFile'] : $this->logFile,
        );
    }
}
