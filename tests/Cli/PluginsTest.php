<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Cli;

use Develate\AntigravityCli\Cli\CommandOutput;
use Develate\AntigravityCli\Cli\CommandRunner;
use Develate\AntigravityCli\Cli\Plugins;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PluginsTest extends TestCase
{
    #[DataProvider('validationTargets')]
    public function test_validation_forwards_the_target_as_one_argument(?string $target): void
    {
        $runner = $this->createMock(CommandRunner::class);
        $output = new CommandOutput(0, 'valid', '');
        $runner->expects(self::once())->method('run')
            ->with($target === null ? ['plugin', 'validate'] : ['plugin', 'validate', $target])
            ->willReturn($output);

        self::assertSame($output, (new Plugins($runner))->validate($target));
    }

    public static function validationTargets(): iterable
    {
        yield 'current directory' => [null];
        yield 'directory' => ['/srv/my plugin'];
        yield 'ZIP' => ['/srv/my plugin.zip'];
        yield 'GitHub URL' => ['https://github.com/example/plugin'];
    }
}
