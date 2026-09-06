<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Transport;

enum RunMode
{
    /** A fresh conversation; the CLI allocates the id. */
    case Start;
    /** An existing conversation, named explicitly by id. */
    case Resume;
}
