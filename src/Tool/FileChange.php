<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tool;

use Develate\AntigravityCli\Value\FileChangeKind;

final readonly class FileChange
{
    public function __construct(
        public string $path,
        public FileChangeKind $kind,
        public bool $failed = false,
    ) {
    }
}
