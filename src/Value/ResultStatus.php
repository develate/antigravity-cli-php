<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

enum ResultStatus: string
{
    case Success = 'SUCCESS';
    case Error = 'ERROR';
    case Cancelled = 'CANCELLED';
    case Unknown = 'UNKNOWN';

    public static function parse(?string $value): self
    {
        return self::tryFrom(strtoupper((string) $value)) ?? self::Unknown;
    }

    public function isSuccess(): bool
    {
        return $this === self::Success;
    }
}
