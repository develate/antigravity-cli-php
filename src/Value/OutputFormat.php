<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

enum OutputFormat: string
{
    case Text = 'text';
    case Json = 'json';
    case StreamJson = 'stream-json';
}
