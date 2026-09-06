<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

final readonly class AgentInfo
{
    public function __construct(public string $name, public string $description = '')
    {
    }
}
