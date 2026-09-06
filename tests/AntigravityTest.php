<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests;

use Develate\AntigravityCli\Antigravity;
use Develate\AntigravityCli\Exception\InvalidOptions;
use Develate\AntigravityCli\SessionOptions;
use Develate\AntigravityCli\Tests\Support\FakeCli;
use Develate\AntigravityCli\Value\Capability;
use PHPUnit\Framework\TestCase;

/**
 * End to end against a stand-in binary that speaks the real flag and NDJSON
 * protocol.
 */
final class AntigravityTest extends TestCase
{
    private function agy(): Antigravity
    {
        return new Antigravity(FakeCli::path());
    }

    public function test_a_one_shot_query_returns_the_answer(): void
    {
        $result = $this->agy()->query('hello');

        self::assertSame('echo: hello', $result->text);
        self::assertTrue($result->isSuccess());
        self::assertNotSame('', $result->conversationId);
    }

    public function test_it_lists_models(): void
    {
        $models = $this->agy()->models();

        self::assertCount(2, $models);
        self::assertSame('gemini-3.8-flash-low', $models[0]->slug);
        self::assertSame('Gemini 3.8 Flash (Low)', $models[0]->label);
    }

    public function test_an_empty_listing_is_empty_rather_than_an_error(): void
    {
        self::assertSame([], $this->agy()->agents());
    }

    public function test_it_reads_the_version(): void
    {
        self::assertSame('1.1.27', $this->agy()->version());
    }

    /**
     * A binary whose version cannot be read is unknown, not incompatible: the
     * interface being present is what decides.
     */
    public function test_an_unreadable_version_is_unknown(): void
    {
        // `/bin/echo` prints its arguments back rather than a version.
        $agy = new Antigravity('/bin/echo');

        self::assertNull($agy->version());
    }

    public function test_a_missing_binary_is_neither_available_nor_compatible(): void
    {
        $agy = new Antigravity('agy-does-not-exist-anywhere');

        self::assertFalse($agy->isAvailable());
        self::assertFalse($agy->isCompatible());
    }

    public function test_a_binary_answering_the_expected_commands_is_compatible(): void
    {
        self::assertTrue($this->agy()->isCompatible());
    }

    public function test_it_reads_the_quota_report(): void
    {
        $quota = $this->agy()->quota();

        self::assertTrue($quota->available);
        self::assertSame('Gemini Models', $quota->entries[0]->group);
        self::assertSame(97.0, $quota->entries[0]->remainingPercent);
    }

    public function test_quota_from_a_silent_binary_is_unavailable(): void
    {
        $quota = (new Antigravity('/bin/echo'))->quota();

        self::assertFalse($quota->available);
        self::assertSame([], $quota->entries);
    }

    public function test_mcp_servers_are_listed(): void
    {
        self::assertSame([], $this->agy()->mcp()->list());
    }

    public function test_resuming_requires_an_explicit_id(): void
    {
        $this->expectException(InvalidOptions::class);

        $this->agy()->resume('  ');
    }

    public function test_resuming_passes_the_id_through_to_the_cli(): void
    {
        $session = $this->agy()->resume('abc-123');

        $result = $session->query('what did I say?');

        self::assertSame('abc-123', $result->conversationId);
    }

    public function test_a_session_reuses_the_conversation_across_turns(): void
    {
        $session = $this->agy()->session();

        $first = $session->query('one');
        $second = $session->query('two');

        self::assertSame($first->conversationId, $second->conversationId);
        self::assertSame('echo: two', $second->text);
    }

    /**
     * One process per turn, each carrying the id forward; the totals the CLI
     * reports keep growing while each turn's own usage does not.
     */
    public function test_per_turn_usage_stays_separate_from_the_conversation_total(): void
    {
        $session = $this->agy()->session();

        $first = $session->query('one');
        $second = $session->query('two');

        self::assertSame(1000, $first->usage?->inputTokens);
        self::assertSame(2000, $second->usage?->inputTokens);
        self::assertSame(3000, $second->cumulativeUsage?->inputTokens, 'the CLI keeps counting the whole conversation');
    }

    public function test_a_persistent_session_answers_several_turns_on_one_process(): void
    {
        $session = $this->agy()->persistentSession();

        $first = $session->query('one');
        $second = $session->query('two');
        $third = $session->query('three');

        self::assertSame('echo: one', $first->text);
        self::assertSame('echo: two', $second->text);
        self::assertSame('echo: three', $third->text);
        self::assertSame($first->conversationId, $third->conversationId);

        $session->close();
    }

    /**
     * The same process reports a growing total, so the per-turn figure has to
     * come from the turn's own steps.
     */
    public function test_a_persistent_session_does_not_recount_earlier_turns(): void
    {
        $session = $this->agy()->persistentSession();

        $first = $session->query('one');
        $second = $session->query('two');

        self::assertSame(1000, $first->usage?->inputTokens);
        self::assertSame(2000, $second->usage?->inputTokens);
        self::assertSame(3000, $second->cumulativeUsage?->inputTokens);

        $session->close();
    }

    public function test_a_closed_session_refuses_further_turns(): void
    {
        $session = $this->agy()->persistentSession();
        $session->query('one');
        $session->close();

        $this->expectException(\LogicException::class);

        $session->query('two');
    }

    public function test_options_reach_the_cli(): void
    {
        $result = $this->agy()->query('hello', new SessionOptions(model: 'claude-sonnet-4-6'));

        self::assertSame('claude-sonnet-4-6', $result->init()?->model);
    }

    public function test_the_working_directory_is_used(): void
    {
        $directory = sys_get_temp_dir();
        $result = $this->agy()->in($directory)->query('hello');

        self::assertSame(realpath($directory), realpath((string) $result->init()?->cwd));
    }

    public function test_capabilities_are_reported(): void
    {
        $agy = $this->agy();

        self::assertTrue($agy->supports(Capability::ResumeByConversationId));
        self::assertFalse($agy->supports(Capability::ImageInput));
    }
}
