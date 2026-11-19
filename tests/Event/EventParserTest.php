<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Event;

use Develate\AntigravityCli\Event\CommandResultEvent;
use Develate\AntigravityCli\Event\EventParser;
use Develate\AntigravityCli\Event\InitEvent;
use Develate\AntigravityCli\Event\ResultEvent;
use Develate\AntigravityCli\Event\StepUpdateEvent;
use Develate\AntigravityCli\Event\UnknownEvent;
use Develate\AntigravityCli\Exception\InvalidStreamJson;
use Develate\AntigravityCli\Tests\Support\Fixtures;
use Develate\AntigravityCli\Value\StepState;
use Develate\AntigravityCli\Value\StepType;
use PHPUnit\Framework\TestCase;

final class EventParserTest extends TestCase
{
    public function test_it_reads_the_init_event(): void
    {
        $events = (new EventParser)->parseLine(Fixtures::init());

        self::assertInstanceOf(InitEvent::class, $events[0]);
        self::assertSame(Fixtures::CONVERSATION, $events[0]->conversationId);
        self::assertSame('gemini-3.8-flash-low', $events[0]->model);
        self::assertSame('request-review', $events[0]->permissionMode);
        self::assertSame(['run_command', 'view_file'], $events[0]->tools);
    }

    public function test_it_reads_a_tool_step(): void
    {
        $line = '{"event":"step_update","step_update":{"conversation_id":"c","step_index":4,"state":"ERROR","step_type":"tool","tool_name":"run_command","duration_seconds":0.07,"tool_info":{"name":"run_command","parameters":{"CommandLine":"ls"},"error":{"type":"TOOL_ERROR","message":"denied"}}}}';

        $event = (new EventParser)->parseLine($line)[0];

        self::assertInstanceOf(StepUpdateEvent::class, $event);
        self::assertSame(StepState::Error, $event->state);
        self::assertSame(StepType::Tool, $event->stepType);
        self::assertSame('run_command', $event->toolName);
        self::assertSame(['CommandLine' => 'ls'], $event->toolParameters);
        self::assertSame('denied', $event->toolError?->message);
        self::assertSame('c#4', $event->stepKey());
        self::assertTrue($event->isTerminal());
    }

    public function test_it_reads_a_result_event(): void
    {
        $line = '{"event":"result","result":{"conversation_id":"c","status":"SUCCESS","response":"hi","duration_seconds":1.5,"num_turns":2,"usage":{"input_tokens":10},"denied_actions":[{"action":"command","display_name":"RunCommand"}]}}';

        $event = (new EventParser)->parseLine($line)[0];

        self::assertInstanceOf(ResultEvent::class, $event);
        self::assertSame('hi', $event->response);
        self::assertSame(2, $event->numTurns);
        self::assertSame(10, $event->cumulativeUsage->inputTokens);
        self::assertCount(1, $event->deniedActions);
    }

    public function test_a_standalone_report_is_its_own_kind_of_event(): void
    {
        $line = '{"event":"command_result","command":{"name":"usage","data":{"description":"..."}}}';

        $event = (new EventParser)->parseLine($line)[0];

        self::assertInstanceOf(CommandResultEvent::class, $event);
        self::assertSame('usage', $event->command);
    }

    public function test_finished_reasons_preserve_unknown_strings_and_ignore_non_strings(): void
    {
        foreach (['print_timeout', 'interrupted', 'error', 'future_reason', null, 123, ['invalid']] as $reason) {
            $payload = ['event' => 'result', 'result' => ['status' => 'ERROR', 'finished_reason' => $reason]];
            $event = (new EventParser)->parseLine(json_encode($payload, JSON_THROW_ON_ERROR))[0];

            self::assertInstanceOf(ResultEvent::class, $event);
            self::assertSame(is_string($reason) ? $reason : null, $event->finishedReason);
            self::assertSame($payload, $event->raw());
        }

        self::assertNull(ResultEvent::fromArray(['event' => 'result', 'result' => []])->finishedReason);
    }

    /**
     * A newer CLI adding an event is not an error; dropping it would lose data
     * a caller may still want.
     */
    public function test_an_unrecognised_event_keeps_its_payload(): void
    {
        $event = (new EventParser)->parseLine('{"event":"brand_new","x":1}')[0];

        self::assertInstanceOf(UnknownEvent::class, $event);
        self::assertSame('brand_new', $event->name());
        self::assertSame(['event' => 'brand_new', 'x' => 1], $event->raw());
    }

    public function test_blank_and_malformed_lines_are_skipped(): void
    {
        $parser = new EventParser;

        self::assertSame([], $parser->parseLine('   '));
        self::assertSame([], $parser->parseLine('ERROR: logging before google.Init: noise'));
        self::assertSame([], $parser->parseLine('{"event":"init"'));
        self::assertSame([], $parser->parseLine('[1,2,3]'));
    }

    public function test_strict_mode_rejects_malformed_lines(): void
    {
        $this->expectException(InvalidStreamJson::class);

        (new EventParser(strict: true))->parseLine('{"event":"init"');
    }

    public function test_an_unknown_step_state_does_not_throw(): void
    {
        $line = '{"event":"step_update","step_update":{"conversation_id":"c","step_index":1,"state":"SOMETHING","step_type":"mystery"}}';

        $event = (new EventParser)->parseLine($line)[0];

        self::assertInstanceOf(StepUpdateEvent::class, $event);
        self::assertSame(StepState::Unknown, $event->state);
        self::assertSame(StepType::Unknown, $event->stepType);
        self::assertFalse($event->isTerminal());
    }
}
