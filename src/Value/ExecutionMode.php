<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

/**
 * The values `agy --mode` accepts.
 *
 * `Plan` is Antigravity's own planning mode. It prefixes the conversation with
 * a planning instruction; it is not a read-only enforcement boundary, and this
 * SDK does not present it as one.
 */
enum ExecutionMode: string
{
    case Default = 'default';
    case Auto = 'auto';
    case AcceptEdits = 'accept-edits';
    case Plan = 'plan';
}
