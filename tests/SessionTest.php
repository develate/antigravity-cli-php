<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests;

use Develate\AntigravityCli\Event\Event;
use Develate\AntigravityCli\Event\StepUpdateEvent;
use Develate\AntigravityCli\Exception\ProcessCancelled;
use Develate\AntigravityCli\Exception\ProcessFailed;
use Develate\AntigravityCli\Exception\RunFailed;
use Develate\AntigravityCli\Session;
use Develate\AntigravityCli\SessionOptions;
use Develate\AntigravityCli\Tests\Support\Fixtures;
use Develate\AntigravityCli\Tests\Support\ScriptedTransport;
use Develate\AntigravityCli\Transport\RunMode;
use Develate\AntigravityCli\Value\ResultStatus;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function test_a_turn_yields_its_text_and_conversation_id(): void
    {
        $session = $this->session([Fixtures::simpleTurn()]);

        $result = $session->query('Say PONG');

        self::assertSame("PONG.\n", $result->text);
        self::assertSame(Fixtures::CONVERSATION, $result->conversationId);
        self::assertSame(ResultStatus::Success, $result->status);
        self::assertTrue($result->isSuccess());
    }

    public function test_the_conversation_id_comes_from_the_cli_and_is_reused(): void
    {
        $transport = new ScriptedTransport([Fixtures::simpleTurn(), Fixtures::resumedTurn()]);
        $session = new Session($transport, new SessionOptions);

        self::assertNull($session->id());

        $session->query('Say PONG');
        self::assertSame(Fixtures::CONVERSATION, $session->id());

        $session->query('What did I ask?');

        self::assertSame(RunMode::Start, $transport->requests[0]->mode);
        self::assertNull($transport->requests[0]->conversationId);
        self::assertSame(RunMode::Resume, $transport->requests[1]->mode);
        self::assertSame(Fixtures::CONVERSATION, $transport->requests[1]->conversationId);
    }

    /**
     * The CLI's result usage counts the whole conversation. A second turn that
     * reported it as its own would charge the first turn twice.
     */
    public function test_a_turns_usage_is_its_own_steps_not_the_conversation_total(): void
    {
        $transport = new ScriptedTransport([Fixtures::simpleTurn(), Fixtures::resumedTurn()]);
        $session = new Session($transport, new SessionOptions);

        $first = $session->query('Say PONG');
        self::assertNotNull($first->usage);
        self::assertSame(5087, $first->usage->inputTokens);

        $second = $session->query('What did I ask?');
        self::assertNotNull($second->usage);
        self::assertSame(13433, $second->usage->inputTokens, 'the second turn must not include the first turn');
        self::assertSame(9, $second->usage->outputTokens);

        self::assertNotNull($second->cumulativeUsage);
        self::assertSame(18520, $second->cumulativeUsage->inputTokens);
    }

    public function test_denied_actions_are_surfaced(): void
    {
        $result = $this->session([Fixtures::deniedTurn()])->query('run something');

        self::assertTrue($result->hasDenials());
        self::assertSame('RunCommand', $result->deniedActions[0]->displayName);

        $commands = $result->commands();
        self::assertCount(1, $commands);
        self::assertSame('pwd && ls -la', $commands[0]->commandLine);
        self::assertTrue($commands[0]->failed);
    }

    /**
     * A step is reported repeatedly as it progresses; merging on its index is
     * what keeps one tool call from being counted as several.
     */
    public function test_repeated_updates_to_one_step_describe_one_tool_call(): void
    {
        $result = $this->session([Fixtures::deniedTurn()])->query('run something');

        self::assertCount(1, $result->toolCalls());
        self::assertSame('run_command', $result->toolCalls()[0]->name);
    }

    public function test_an_unsuccessful_turn_is_reported_rather_than_thrown(): void
    {
        $session = $this->session([[
            Fixtures::init(),
            '{"event":"result","result":{"conversation_id":"","status":"ERROR","response":"","error":"stream input message is missing the \"event\" field","duration_seconds":0,"num_turns":0,"usage":{}}}',
        ]]);

        $result = $session->query('anything');

        self::assertFalse($result->isSuccess());
        self::assertSame(ResultStatus::Error, $result->status);
        self::assertStringContainsString('missing the "event" field', (string) $result->error);
    }

    /**
     * The CLI can end without answering. There is no result to report, so the
     * turn fails rather than returning a silently empty one.
     */
    public function test_result_or_fail_turns_an_unsuccessful_turn_into_an_exception(): void
    {
        $run = $this->session([[
            Fixtures::init(),
            '{"event":"result","result":{"conversation_id":"c","status":"ERROR","response":"","error":"quota exhausted","num_turns":0,"usage":{}}}',
        ]])->stream('anything');

        self::assertFalse($run->result()->isSuccess());

        $this->expectException(RunFailed::class);
        $this->expectExceptionMessage('quota exhausted');

        $run->resultOrFail();
    }

    public function test_a_stream_that_ends_before_the_result_fails(): void
    {
        $session = $this->session([[Fixtures::init()]], exitCode: 1);

        $this->expectException(ProcessFailed::class);

        $session->query('anything');
    }

    public function test_unknown_events_are_carried_through(): void
    {
        $session = $this->session([[
            Fixtures::init(),
            '{"event":"something_new","payload":{"a":1}}',
            '{"event":"result","result":{"conversation_id":"c","status":"SUCCESS","response":"ok","num_turns":1,"usage":{}}}',
        ]]);

        $result = $session->query('hi');

        $names = array_map(static fn (Event $event): string => $event->name(), $result->events());
        self::assertContains('something_new', $names);
        self::assertSame('ok', $result->text);
    }

    public function test_malformed_lines_do_not_derail_a_turn(): void
    {
        $session = $this->session([[
            Fixtures::init(),
            'not json at all',
            '{"event":"result","result":{"conversation_id":"c","status":"SUCCESS","response":"ok","num_turns":1,"usage":{}}}',
        ]]);

        self::assertSame('ok', $session->query('hi')->text);
    }

    public function test_a_cancelled_run_stops(): void
    {
        $session = $this->session([Fixtures::simpleTurn()]);
        $run = $session->stream('Say PONG');
        $run->cancel();

        $this->expectException(ProcessCancelled::class);

        $run->result();
    }

    public function test_a_run_streams_events_as_they_arrive(): void
    {
        $session = $this->session([Fixtures::simpleTurn()]);

        $seen = [];
        foreach ($session->stream('Say PONG') as $event) {
            $seen[] = $event->name();
        }

        self::assertSame('init', $seen[0]);
        self::assertSame('result', $seen[array_key_last($seen)]);
        self::assertContains('step_update', $seen);
    }

    public function test_listeners_see_every_event(): void
    {
        $session = $this->session([Fixtures::simpleTurn()]);

        $deltas = [];
        $session->onEvent(static function (Event $event) use (&$deltas): void {
            if ($event instanceof StepUpdateEvent && $event->textDelta !== null) {
                $deltas[] = $event->textDelta;
            }
        });

        $session->query('Say PONG');

        self::assertSame(['PONG.', "\n"], $deltas);
    }

    public function test_a_session_runs_one_turn_at_a_time(): void
    {
        $session = $this->session([Fixtures::simpleTurn(), Fixtures::resumedTurn()]);
        $session->stream('first');

        $this->expectException(\LogicException::class);

        $session->stream('second');
    }

    /** @param list<list<string>> $turns */
    private function session(array $turns, int $exitCode = 0): Session
    {
        return new Session(new ScriptedTransport($turns, $exitCode), new SessionOptions);
    }
}
