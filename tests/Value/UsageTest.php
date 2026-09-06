<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Value;

use Develate\AntigravityCli\Value\Usage;
use PHPUnit\Framework\TestCase;

final class UsageTest extends TestCase
{
    public function test_it_reads_the_cli_field_names(): void
    {
        $usage = Usage::fromArray([
            'input_tokens' => 5087,
            'output_tokens' => 3,
            'thinking_tokens' => 2,
            'cache_read_tokens' => 8128,
            'total_tokens' => 5090,
        ]);

        self::assertSame(5087, $usage->inputTokens);
        self::assertSame(3, $usage->outputTokens);
        self::assertSame(2, $usage->thinkingTokens);
        self::assertSame(8128, $usage->cacheReadTokens);
        self::assertSame(5090, $usage->totalTokens());
    }

    public function test_a_missing_payload_is_empty_rather_than_wrong(): void
    {
        $usage = Usage::fromArray(null);

        self::assertTrue($usage->isEmpty());
        self::assertSame(0, $usage->totalTokens());
    }

    public function test_steps_add_up(): void
    {
        $total = Usage::fromArray(['input_tokens' => 10, 'output_tokens' => 1, 'total_tokens' => 11])
            ->plus(Usage::fromArray(['input_tokens' => 20, 'output_tokens' => 2, 'total_tokens' => 22]));

        self::assertSame(30, $total->inputTokens);
        self::assertSame(3, $total->outputTokens);
        self::assertSame(33, $total->totalTokens());
    }

    public function test_a_difference_against_a_baseline_gives_one_turns_consumption(): void
    {
        $before = Usage::fromArray(['input_tokens' => 5087, 'output_tokens' => 3]);
        $after = Usage::fromArray(['input_tokens' => 18520, 'output_tokens' => 12]);

        $turn = $after->minus($before);

        self::assertNotNull($turn);
        self::assertSame(13433, $turn->inputTokens);
        self::assertSame(9, $turn->outputTokens);
    }

    /**
     * A cumulative counter never goes backwards, so a negative difference means
     * the baseline was not this conversation's. Unknown beats wrong.
     */
    public function test_a_baseline_from_another_conversation_yields_no_number(): void
    {
        $usage = Usage::fromArray(['input_tokens' => 100]);
        $laterBaseline = Usage::fromArray(['input_tokens' => 500]);

        self::assertNull($usage->minus($laterBaseline));
    }
}
