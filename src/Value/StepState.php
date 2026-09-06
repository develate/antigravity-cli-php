<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

enum StepState: string
{
    case Active = 'ACTIVE';
    case Done = 'DONE';
    case Error = 'ERROR';
    case Unknown = 'UNKNOWN';

    public static function parse(?string $value): self
    {
        return self::tryFrom(strtoupper((string) $value)) ?? self::Unknown;
    }

    public function isTerminal(): bool
    {
        return $this === self::Done || $this === self::Error;
    }
}
