<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Cli;

use Develate\AntigravityCli\Exception\InvalidOptions;

/**
 * `agy plugin`.
 */
final readonly class Plugins
{
    public function __construct(private CommandRunner $runner)
    {
    }

    /** @return list<string> */
    public function list(): array
    {
        return $this->runner->run(['plugin', 'list'])->lines();
    }

    public function install(string $target): CommandOutput
    {
        self::assertTarget($target);

        return $this->runner->run(['plugin', 'install', $target], 300.0);
    }

    public function uninstall(string $name): CommandOutput
    {
        self::assertTarget($name);

        return $this->runner->run(['plugin', 'uninstall', $name]);
    }

    public function enable(string $name): CommandOutput
    {
        self::assertTarget($name);

        return $this->runner->run(['plugin', 'enable', $name]);
    }

    public function disable(string $name): CommandOutput
    {
        self::assertTarget($name);

        return $this->runner->run(['plugin', 'disable', $name]);
    }

    public function validate(?string $path = null): CommandOutput
    {
        return $this->runner->run($path === null ? ['plugin', 'validate'] : ['plugin', 'validate', $path]);
    }

    private static function assertTarget(string $target): void
    {
        if (trim($target) === '') {
            throw new InvalidOptions('A plugin name must not be empty.');
        }
    }
}
