<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

final readonly class ModelInfo
{
    public function __construct(public string $slug, public string $label)
    {
    }
}
