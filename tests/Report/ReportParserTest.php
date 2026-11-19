<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Report;

use Develate\AntigravityCli\Report\ReportParser;
use PHPUnit\Framework\TestCase;

final class ReportParserTest extends TestCase
{
    public function test_it_reads_the_usage_report(): void
    {
        $text = "Gemini Models\tWeekly Limit Remaining\t97%\t2026-09-13T21:32:08Z\n"
            ."Claude and GPT models\tWeekly Limit Remaining\t95%\t2026-09-13T21:35:38Z\n";

        $report = (new ReportParser)->parseQuota($text);

        self::assertTrue($report->available);
        self::assertCount(2, $report->entries);
        self::assertSame('Gemini Models', $report->entries[0]->group);
        self::assertSame(97.0, $report->entries[0]->remainingPercent);
        self::assertSame('2026-09-13', $report->entries[0]->resetsAt?->format('Y-m-d'));
        self::assertSame(95.0, $report->entryFor('claude and gpt models')?->remainingPercent);
    }

    /**
     * The layout of these reports is not a contract. When it is not recognised
     * the answer is "unavailable" with the text kept, never a made-up number.
     */
    public function test_unrecognised_output_is_unavailable_and_invents_nothing(): void
    {
        $report = (new ReportParser)->parseQuota("You are not logged into Antigravity.\n", 'stderr noise');

        self::assertFalse($report->available);
        self::assertSame([], $report->entries);
        self::assertStringContainsString('not logged into', $report->text);
        self::assertSame('stderr noise', $report->diagnostics);
    }

    public function test_empty_output_is_unavailable(): void
    {
        self::assertFalse((new ReportParser)->parseQuota('')->available);
    }

    public function test_a_failed_quota_command_cannot_supply_account_quota(): void
    {
        $text = "Gemini Models\tWeekly Limit Remaining\t97%\n";
        $report = (new ReportParser)->parseQuota($text, 'Quota is unavailable with API keys.', 2);

        self::assertFalse($report->available);
        self::assertSame([], $report->entries);
        self::assertSame($text, $report->text);
        self::assertSame('Quota is unavailable with API keys.', $report->diagnostics);
        self::assertSame(2, $report->exitCode);
    }

    public function test_a_row_with_missing_columns_keeps_what_it_has(): void
    {
        $report = (new ReportParser)->parseQuota("Gemini Models\tWeekly Limit Remaining\n");

        self::assertTrue($report->available);
        self::assertSame('Gemini Models', $report->entries[0]->group);
        self::assertNull($report->entries[0]->remainingPercent);
        self::assertNull($report->entries[0]->resetsAt);
    }

    public function test_an_unparsable_reset_time_is_null_rather_than_now(): void
    {
        $report = (new ReportParser)->parseQuota("Gemini\tWeekly\t50%\tsometime soon\n");

        self::assertSame(50.0, $report->entries[0]->remainingPercent);
        self::assertNull($report->entries[0]->resetsAt);
    }
}
