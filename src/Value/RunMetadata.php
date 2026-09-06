<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

final readonly class RunMetadata
{
    public function __construct(
        public ?string $cliVersion,
        public ?string $model,
        public string $cwd,
    ) {
    }
}
