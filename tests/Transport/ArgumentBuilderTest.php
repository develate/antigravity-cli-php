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
use Develate\AntigravityCli\Value\ModelCapabilities;
use Develate\AntigravityCli\Value\ModelInfo;
use PHPUnit\Framework\Attributes\DataProvider;
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
                model: 'gemini-3.8-flash',
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

        self::assertContains('--model=gemini-3.8-flash', $command);
        self::assertContains('--effort=high', $command);
        self::assertContains('--mode=accept-edits', $command);
        self::assertContains('--sandbox', $command);
        self::assertContains('--add-dir=/tmp/a', $command);
        self::assertContains('--add-dir=/tmp/b', $command);
    }

    #[DataProvider('modelsWithoutAdjustableEffort')]
    public function test_fixed_models_omit_saved_effort_on_new_and_resumed_commands(string $model): void
    {
        $options = new SessionOptions(
            model: $model,
            effort: Effort::High,
            agent: 'reviewer',
            mode: ExecutionMode::AcceptEdits,
            sandbox: true,
            additionalDirectories: ['/tmp/extra'],
        );

        foreach ([RunMode::Start, RunMode::Resume] as $mode) {
            $command = (new ArgumentBuilder)->stream($this->request(
                mode: $mode,
                conversationId: 'saved-conversation',
                session: $options,
                run: new RunOptions(printTimeout: 12.0, schema: ['type' => 'object']),
            ));

            self::assertSame([
                'agy', '--print=', '--input-format=stream-json', '--output-format=stream-json',
                ...($mode === RunMode::Resume ? ['--conversation=saved-conversation'] : []),
                '--model='.$model, '--agent=reviewer', '--mode=accept-edits', '--sandbox',
                '--add-dir=/tmp/extra', '--print-timeout=12s', '--json-schema={"type":"object"}',
            ], $command);
        }

        self::assertFalse(ModelCapabilities::supportsReasoningEffort($model));
        self::assertFalse((new ModelInfo($model, 'Display name'))->supportsReasoningEffort());
        self::assertSame(Effort::High, $options->effort);
    }

    /** @return iterable<string, array{string}> */
    public static function modelsWithoutAdjustableEffort(): iterable
    {
        foreach ([
            'claude-opus-4-6-thinking', 'CLAUDE-OPUS-4-6-THINKING', 'claude-sonnet-4-6',
            'gemini-3.8-flash-low', 'gemini-3.8-flash-medium', 'gemini-3.8-flash-high',
            'GEMINI-3.1-PRO-HIGH', 'gemini-future-medium',
            'gemini-4-neon-xhigh', 'GEMINI-4-HYDROGEN-XHIGH', 'gemini-future-max',
            'gemini-pro-orchestrator', 'gemini-pro-orchestrator-argon',
            'gemini-pro-orchestrator-neon', 'GEMINI-PRO-ORCHESTRATOR-NEON',
        ] as $model) {
            yield $model => [$model];
        }
    }

    #[DataProvider('modelsWithAdjustableEffort')]
    public function test_supported_and_unknown_models_preserve_effort_without_discovery(?string $model): void
    {
        $command = (new ArgumentBuilder)->stream($this->request(
            session: new SessionOptions(model: $model, effort: Effort::High),
        ));

        self::assertSame([
            'agy', '--print=', '--input-format=stream-json', '--output-format=stream-json',
            ...($model !== null && $model !== '' ? ['--model='.$model] : []),
            '--effort=high',
        ], $command);
        self::assertTrue(ModelCapabilities::supportsReasoningEffort($model));
        self::assertTrue((new ModelInfo($model ?? '', 'Display name'))->supportsReasoningEffort());
    }

    /** @return iterable<string, array{?string}> */
    public static function modelsWithAdjustableEffort(): iterable
    {
        yield 'null default' => [null];
        yield 'empty default' => [''];
        foreach ([
            'gemini-3.8-flash', 'gemini-3.1-pro', 'custom-model', 'custom-high',
            'vendor/custom-thinking', 'claude-future-thinking', 'claude-opus-4-6',
            'gpt-oss-120b-medium', 'gemini-pro-orchestrator-custom',
        ] as $model) {
            yield $model => [$model];
        }
    }

    public function test_no_selected_effort_adds_no_flag_for_a_supported_model(): void
    {
        self::assertSame([
            'agy', '--print=', '--input-format=stream-json', '--output-format=stream-json',
            '--model=gemini-3.8-flash',
        ], (new ArgumentBuilder)->stream($this->request(
            session: new SessionOptions(model: 'gemini-3.8-flash'),
        )));
    }

    #[DataProvider('newEfforts')]
    public function test_extended_efforts_are_passed_through_for_base_and_default_models(?string $model, Effort $effort): void
    {
        foreach ([RunMode::Start, RunMode::Resume] as $mode) {
            $command = (new ArgumentBuilder)->stream($this->request(
                mode: $mode,
                conversationId: 'saved-conversation',
                session: new SessionOptions(model: $model, effort: $effort),
            ));

            self::assertContains('--effort='.$effort->value, $command);
        }
    }

    public static function newEfforts(): iterable
    {
        yield 'neon xhigh' => ['gemini-4-neon', Effort::XHigh];
        yield 'hydrogen xhigh' => ['gemini-4-hydrogen', Effort::XHigh];
        yield 'custom max' => ['custom-model', Effort::Max];
        yield 'null default' => [null, Effort::XHigh];
        yield 'empty default' => ['', Effort::Max];
    }

    #[DataProvider('executionModes')]
    public function test_explicit_modes_override_the_inherited_default(ExecutionMode $mode): void
    {
        foreach ([RunMode::Start, RunMode::Resume] as $runMode) {
            $command = (new ArgumentBuilder)->stream($this->request(
                mode: $runMode,
                conversationId: 'saved-conversation',
                session: (new SessionOptions)->withMode($mode),
            ));

            self::assertContains('--mode='.$mode->value, $command);
        }
    }

    public static function executionModes(): iterable
    {
        yield 'default' => [ExecutionMode::Default];
        yield 'auto' => [ExecutionMode::Auto];
        yield 'accept edits' => [ExecutionMode::AcceptEdits];
        yield 'plan' => [ExecutionMode::Plan];
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
