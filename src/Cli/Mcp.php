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
     * @param  array<string, string>  $env  environment variables for a stdio server
     * @param  array<string, string>  $headers  HTTP request headers
     */
    public function add(string $name, array $arguments, array $env = [], array $headers = [], ?string $type = null): CommandOutput
    {
        self::assertName($name);

        if (!array_is_list($arguments) || $arguments === [] || !is_string($arguments[0]) || trim($arguments[0]) === '') {
            throw new InvalidOptions('An MCP server needs a command to run.');
        }

        foreach ($arguments as $argument) {
            if (!is_string($argument) || str_contains($argument, "\0")) {
                throw new InvalidOptions('MCP command arguments must be strings without null bytes.');
            }
        }
        if ($type !== null && !in_array($type, ['stdio', 'http'], true)) {
            throw new InvalidOptions('The MCP server type must be stdio or http.');
        }

        $flags = $type === null ? [] : ['--type='.$type];
        foreach ($env as $key => $value) {
            if (!is_string($key) || $key === '' || strpbrk($key, "=\0\r\n") !== false || !is_string($value) || str_contains($value, "\0")) {
                throw new InvalidOptions('MCP environment variables need valid names and string values without null bytes.');
            }
            $flags[] = '--env='.$key.'='.$value;
        }
        foreach ($headers as $key => $value) {
            if (!is_string($key) || !preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $key) || !is_string($value) || strpbrk($value, "\0\r\n") !== false) {
                throw new InvalidOptions('MCP headers need valid names and single-line string values.');
            }
            $flags[] = '--header='.$key.': '.$value;
        }

        return $this->runner->run(['mcp', 'add', ...$flags, $name, '--', ...$arguments]);
    }

    /** @param list<string> $arguments @param array<string, string> $env */
    public function addStdio(string $name, string $command, array $arguments = [], array $env = []): CommandOutput
    {
        if (!array_is_list($arguments)) {
            throw new InvalidOptions('MCP command arguments must be a list.');
        }

        return $this->add($name, [$command, ...$arguments], env: $env, type: 'stdio');
    }

    /** @param array<string, string> $headers */
    public function addHttp(string $name, string $url, array $headers = []): CommandOutput
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new InvalidOptions('An HTTP MCP server needs an http or https URL.');
        }

        return $this->add($name, [$url], headers: $headers, type: 'http');
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
        if (trim($name) === '' || str_starts_with($name, '-') || strpbrk($name, "\0\r\n") !== false) {
            throw new InvalidOptions('An MCP server name must not be empty, start with a dash, or contain null bytes or line breaks.');
        }
    }
}
