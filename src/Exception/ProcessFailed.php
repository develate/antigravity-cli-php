<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Exception;

final class ProcessFailed extends AntigravityException
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stderr = '',
    ) {
        parent::__construct(sprintf(
            'Antigravity exited with code %d%s',
            $exitCode,
            $stderr === '' ? '.' : ': '.trim($stderr),
        ), $exitCode);
    }
}
