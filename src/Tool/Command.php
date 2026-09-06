<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tool;

final readonly class Command
{
    public function __construct(
        public string $commandLine,
        public bool $failed,
        public ?string $error = null,
    ) {
    }
}
