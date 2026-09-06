<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

final readonly class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $thinkingTokens = 0,
        public int $cacheReadTokens = 0,
        public ?int $reportedTotalTokens = null,
    ) {
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): self
    {
        $data ??= [];
        $total = $data['total_tokens'] ?? $data['totalTokens'] ?? null;

        return new self(
            inputTokens: self::integer($data, 'input_tokens', 'inputTokens'),
            outputTokens: self::integer($data, 'output_tokens', 'outputTokens'),
            thinkingTokens: self::integer($data, 'thinking_tokens', 'thinkingTokens'),
            cacheReadTokens: self::integer($data, 'cache_read_tokens', 'cacheReadTokens'),
            reportedTotalTokens: is_numeric($total) ? (int) $total : null,
        );
    }

    public static function zero(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->inputTokens === 0
            && $this->outputTokens === 0
            && $this->thinkingTokens === 0
            && $this->cacheReadTokens === 0;
    }

    /**
     * The CLI's own total when it sent one, and the sum of the parts otherwise.
     */
    public function totalTokens(): int
    {
        return $this->reportedTotalTokens ?? ($this->inputTokens + $this->outputTokens);
    }

    public function plus(self $other): self
    {
        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            thinkingTokens: $this->thinkingTokens + $other->thinkingTokens,
            cacheReadTokens: $this->cacheReadTokens + $other->cacheReadTokens,
            reportedTotalTokens: $this->reportedTotalTokens === null && $other->reportedTotalTokens === null
                ? null
                : ($this->reportedTotalTokens ?? 0) + ($other->reportedTotalTokens ?? 0),
        );
    }

    /**
     * This usage minus an earlier cumulative reading.
     *
     * Only meaningful between two readings of the same cumulative counter.
     * Counters never decrease, so a negative component means the baseline did
     * not belong to this conversation and the difference is not trustworthy;
     * `null` says so rather than reporting a wrong number.
     */
    public function minus(self $baseline): ?self
    {
        $input = $this->inputTokens - $baseline->inputTokens;
        $output = $this->outputTokens - $baseline->outputTokens;
        $thinking = $this->thinkingTokens - $baseline->thinkingTokens;
        $cacheRead = $this->cacheReadTokens - $baseline->cacheReadTokens;

        if ($input < 0 || $output < 0 || $thinking < 0 || $cacheRead < 0) {
            return null;
        }

        $total = $this->reportedTotalTokens === null || $baseline->reportedTotalTokens === null
            ? null
            : $this->reportedTotalTokens - $baseline->reportedTotalTokens;

        if ($total !== null && $total < 0) {
            return null;
        }

        return new self($input, $output, $thinking, $cacheRead, $total);
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $snake, string $camel): int
    {
        $value = $data[$snake] ?? $data[$camel] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
