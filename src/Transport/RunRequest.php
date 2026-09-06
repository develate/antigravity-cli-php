<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Transport;

use Develate\AntigravityCli\RunOptions;
use Develate\AntigravityCli\SessionOptions;

final readonly class RunRequest
{
    public function __construct(
        public RunMode $mode,
        public ?string $conversationId,
        public string $prompt,
        public SessionOptions $session,
        public RunOptions $run = new RunOptions,
        public ?\Closure $isCancelled = null,
    ) {
    }

    public function cwd(): ?string
    {
        return $this->session->cwd === '' ? null : $this->session->cwd;
    }

    public function timeout(): ?float
    {
        return $this->run->timeout;
    }

    public function withCancellation(\Closure $isCancelled): self
    {
        return new self(
            $this->mode,
            $this->conversationId,
            $this->prompt,
            $this->session,
            $this->run,
            $isCancelled,
        );
    }
}
