<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

enum FileChangeKind: string
{
    case Write = 'write';
    case Edit = 'edit';
    case Delete = 'delete';
}
