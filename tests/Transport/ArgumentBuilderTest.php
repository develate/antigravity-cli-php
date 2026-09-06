<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Transport;

use Develate\AntigravityCli\Exception\InvalidOptions;
use Develate\AntigravityCli\RunOptions;
use Develate\AntigravityCli\SessionOptions;
use Develate\AntigravityCli\Transport\ArgumentBuilder;
use Develate\AntigravityCli\Transport\RunMode;
use Develate\AntigravityCli\Transport\RunRequest;
use Develate\AntigravityCli\Value\Effort;
use Develate\AntigravityCli\Value\ExecutionMode;
use PHPUnit\Framework\TestCase;

final class ArgumentBuilderTest extends TestCase
{
    public function test_a_new_conversation_streams_over_stdin(): void
    {
        $command = (new ArgumentBuilder)->stream($this->request());

        self::assertSame([
            'agy',
            '--print=',
            '--input-format=stream-json',
            '--output-format=stream-json',
        ], $command);
    }

    /**
     * `agy --print` takes the next argument as its prompt, so a detached flag
     * value would be swallowed and the CLI would report an empty prompt.
     */
    public function test_every_flag_carries_its_value_attached(): void
    {
        $command = (new ArgumentBuilder)->stream($this->request(
            session: new SessionOptions(
                cwd: '/tmp/p',
                model: 'gemini-3.8-flash-low',
                effort: Effort::High,
                mode: ExecutionMode::AcceptEdits,
                sandbox: true,
                additionalDirectories: ['/tmp/a', '/tmp/b'],
            ),
        ));

        foreach ($command as $argument) {
            if (str_starts_with($argument, '--') && !str_contains($argument, '=')) {
                self::assertContains($argument, ['--sandbox', '--new-project', '--disable-slash-commands', '--dangerously-skip-permissions']);
            }
        }

        self::assertContains('--model=gemini-3.8-flash-low', $command);
        self::assertContains('--effort=high', $command);
        self::assertContains('--mode=accept-edits', $command);
        self::assertContains('--sandbox', $command);
        self::assertContains('--add-dir=/tmp/a', $command);
        self::assertContains('--add-dir=/tmp/b', $command);
    }

    public function test_resuming_names_the_conversation_explicitly(): void
    {
        $command = (new ArgumentBuilder)->stream($this->request(
            mode: RunMode::Resume,
            conversationId: 'abc-123',
        ));

        self::assertContains('--conversation=abc-123', $command);
        self::assertNotContains('--continue', $command);
        self::assertNotContains('-c', $command);
    }

    public function test_resuming_without_an_id_is_refused(): void
    {
        $this->expectException(InvalidOptions::class);

        (new ArgumentBuilder)->stream($this->request(mode: RunMode::Resume));
    }

    public function test_the_permission_bypass_and_the_sandbox_cannot_be_combined(): void
    {
        $this->expectException(InvalidOptions::class);

        new SessionOptions(sandbox: true, dangerouslyBypassPermissions: true);
    }

    public function test_the_permission_bypass_is_passed_through_when_asked_for(): void
    {
        $command = (new ArgumentBuilder)->stream($this->request(
            session: new SessionOptions(dangerouslyBypassPermissions: true),
        ));

        self::assertContains('--dangerously-skip-permissions', $command);
    }

    public function test_a_schema_is_encoded_as_json(): void
    {
        $command = (new ArgumentBuilder)->stream($this->request(
            run: new RunOptions(schema: ['type' => 'object']),
        ));

        self::assertContains('--json-schema={"type":"object"}', $command);
    }

    public function test_a_print_timeout_uses_the_duration_syntax_the_cli_parses(): void
    {
        $command = (new ArgumentBuilder)->stream($this->request(run: new RunOptions(printTimeout: 90.0)));

        self::assertContains('--print-timeout=90s', $command);
    }

    public function test_a_turn_is_one_ndjson_user_message(): void
    {
        $message = ArgumentBuilder::inputMessage('Say hello');

        self::assertSame('{"event":"user","message":{"content":"Say hello"}}'."\n", $message);
    }

    public function test_a_report_is_a_standalone_print_command(): void
    {
        $command = (new ArgumentBuilder)->report('/usage');

        self::assertSame(['agy', '--print=/usage', '--output-format=stream-json'], $command);
    }

    public function test_a_report_must_be_a_slash_command(): void
    {
        $this->expectException(InvalidOptions::class);

        (new ArgumentBuilder)->report('usage');
    }

    private function request(
        RunMode $mode = RunMode::Start,
        ?string $conversationId = null,
        ?SessionOptions $session = null,
        ?RunOptions $run = null,
    ): RunRequest {
        return new RunRequest(
            mode: $mode,
            conversationId: $conversationId,
            prompt: 'hello',
            session: $session ?? new SessionOptions,
            run: $run ?? new RunOptions,
        );
    }
}
