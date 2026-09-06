<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Exception;

final class InvalidStreamJson extends AntigravityException
{
    public function __construct(
        public readonly string $jsonLine,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'Antigravity emitted invalid stream JSON'.($previous === null ? '.' : ': '.$previous->getMessage()),
            0,
            $previous,
        );
    }
}
