<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Report;

final readonly class QuotaEntry
{
    public function __construct(
        public string $group,
        public string $limit,
        public ?float $remainingPercent,
        public ?\DateTimeImmutable $resetsAt,
    ) {
    }
}
