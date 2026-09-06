<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Support;

/**
 * Installs the fake `agy` as an executable on disk, so the SDK reaches it the
 * same way it reaches a real one.
 */
final class FakeCli
{
    private static ?string $path = null;

    public static function path(): string
    {
        if (self::$path !== null && is_file(self::$path)) {
            return self::$path;
        }

        $directory = sys_get_temp_dir().'/antigravity-cli-php-tests';
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create '.$directory);
        }

        $path = $directory.'/agy';
        $script = __DIR__.'/fake-agy.php';
        file_put_contents($path, "#!/bin/sh\nexec ".escapeshellarg(PHP_BINARY).' '.escapeshellarg($script)." \"\$@\"\n");
        chmod($path, 0o755);

        return self::$path = $path;
    }
}
