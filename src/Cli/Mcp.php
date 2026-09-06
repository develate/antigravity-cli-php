<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Cli;

use Develate\AntigravityCli\Exception\InvalidOptions;

/**
 * `agy mcp`.
 *
 * These commands edit the CLI's own configuration files, which are shared by
 * every Antigravity session on the machine. Each one therefore runs only when a
 * caller asks for it by name; nothing here is invoked as a side effect of a run.
 */
final readonly class Mcp
{
    public function __construct(private CommandRunner $runner)
    {
    }

    /** @return list<string> the configured server names */
    public function list(): array
    {
        $output = $this->runner->run(['mcp', 'list']);

        $names = [];
        foreach ($output->lines() as $line) {
            if (stripos($line, 'No MCP servers') !== false) {
                return [];
            }
            $name = trim(explode(' ', ltrim($line, "-* \t"))[0] ?? '');
            $name = rtrim($name, ':');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $arguments  the server command and its arguments
     */
    public function add(string $name, array $arguments): CommandOutput
    {
        self::assertName($name);

        if ($arguments === []) {
            throw new InvalidOptions('An MCP server needs a command to run.');
        }

        return $this->runner->run(['mcp', 'add', $name, ...$arguments]);
    }

    public function remove(string $name): CommandOutput
    {
        self::assertName($name);

        return $this->runner->run(['mcp', 'remove', $name]);
    }

    public function enable(string $name): CommandOutput
    {
        self::assertName($name);

        return $this->runner->run(['mcp', 'enable', $name]);
    }

    public function disable(string $name): CommandOutput
    {
        self::assertName($name);

        return $this->runner->run(['mcp', 'disable', $name]);
    }

    private static function assertName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidOptions('An MCP server name must not be empty.');
        }
    }
}
