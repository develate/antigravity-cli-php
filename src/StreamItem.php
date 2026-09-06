<?php

declare(strict_types=1);

namespace Develate\AntigravityCli;

interface StreamItem
{
    /** @return array<string, mixed> */
    public function raw(): array;
}
