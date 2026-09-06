<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Process;

use Develate\AntigravityCli\Exception\AntigravityNotFound;
use Symfony\Component\Process\ExecutableFinder;

final class AntigravityExecutable
{
    public static function assertAvailable(string $binary): void
    {
        if (!self::isAvailable($binary)) {
            throw new AntigravityNotFound(
                sprintf('Antigravity binary "%s" was not found or is not executable.', $binary),
            );
        }
    }

    public static function isAvailable(string $binary): bool
    {
        if ($binary === '') {
            return false;
        }

        $hasPath = str_contains($binary, '/') || str_contains($binary, '\\');

        return $hasPath
            ? is_file($binary) && is_executable($binary)
            : (new ExecutableFinder)->find($binary) !== null;
    }
}
