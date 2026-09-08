<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Cli;

use Develate\AntigravityCli\Cli\CommandOutput;
use Develate\AntigravityCli\Cli\CommandRunner;
use Develate\AntigravityCli\Cli\Mcp;
use Develate\AntigravityCli\Exception\InvalidOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class McpTest extends TestCase
{
    #[DataProvider('commands')]
    public function test_commands(string $method, array $arguments, array $expected): void
    {
        $runner = $this->createMock(CommandRunner::class);
        $output = new CommandOutput(0, 'done', 'diagnostics');
        $runner->expects(self::once())->method('run')->with($expected)->willReturn($output);

        self::assertSame($output, (new Mcp($runner))->{$method}(...$arguments));
    }

    public static function commands(): iterable
    {
        yield ['add', ['fs', ['npx', '-y', 'server', '/work space']], ['mcp', 'add', 'fs', '--', 'npx', '-y', 'server', '/work space']];
        yield ['addStdio', ['fs', 'docker', ['run', '-i', 'server'], ['TOKEN' => 'a=b c']], ['mcp', 'add', '--type=stdio', '--env=TOKEN=a=b c', 'fs', '--', 'docker', 'run', '-i', 'server']];
        yield ['addHttp', ['api', 'https://example.com/mcp', ['Authorization' => 'Bearer token']], ['mcp', 'add', '--type=http', '--header=Authorization: Bearer token', 'api', '--', 'https://example.com/mcp']];
        foreach (['remove', 'enable', 'disable'] as $method) {
            yield [$method, ['api'], ['mcp', $method, 'api']];
        }
    }

    #[DataProvider('invalidOptions')]
    public function test_invalid_options_do_not_start_a_process(string $method, array $arguments): void
    {
        $runner = $this->createMock(CommandRunner::class);
        $runner->expects(self::never())->method('run');
        $this->expectException(InvalidOptions::class);
        (new Mcp($runner))->{$method}(...$arguments);
    }

    public static function invalidOptions(): iterable
    {
        yield ['add', ['x', []]];
        yield ['add', ['x', ['']]];
        yield ['add', ['x', ['cmd', 123]]];
        yield ['add', ['x', ['cmd'], [], [], 'sse']];
        yield ['addStdio', ['x', 'cmd', [], ['BAD=KEY' => 'value']]];
        yield ['addStdio', ['x', 'cmd', [], ['KEY' => false]]];
        yield ['addHttp', ['x', 'file:///tmp/server']];
        yield ['addHttp', ['x', 'https://example.com', ['Bad:Name' => 'value']]];
        yield ['addHttp', ['x', 'https://example.com', ['Authorization' => "value\r\ninjected: yes"]]];
        foreach (['add', 'remove', 'enable', 'disable'] as $method) {
            yield [$method, $method === 'add' ? ['--help', ['cmd']] : ['--help']];
        }
    }
}
