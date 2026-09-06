<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

enum StepType: string
{
    case UserInput = 'user_input';
    case AgentResponse = 'agent_response';
    case Tool = 'tool';
    case SystemMessage = 'system_message';
    case Unknown = 'unknown';

    public static function parse(?string $value): self
    {
        return self::tryFrom(strtolower((string) $value)) ?? self::Unknown;
    }
}
