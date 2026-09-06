<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Event;

use Develate\AntigravityCli\StreamItem;

interface Event extends StreamItem
{
    /** The value of the payload's `event` field, verbatim. */
    public function name(): string;
}
