<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Process;

use Develate\AntigravityCli\Exception\AntigravityNotFound;
use Develate\AntigravityCli\Exception\ProcessCancelled;
use Develate\AntigravityCli\Exception\ProcessTimedOut;
use Develate\AntigravityCli\Process\AntigravityProcess;
use PHPUnit\Framework\TestCase;

/**
 * These run a PHP child process rather than `agy`, so the behaviour under test
 * is the SDK's own: what it passes, what it reads, and what it leaves running.
 */
final class AntigravityProcessTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['AGY_INHERITED'], $_SERVER['GEMINI_API_KEY']);
        putenv('AGY_INHERITED');
        putenv('GEMINI_API_KEY');
    }

    public function test_the_given_environment_is_merged_onto_the_inherited_one(): void
    {
        putenv('AGY_INHERITED=kept');
        $_SERVER['AGY_INHERITED'] = 'kept';

        $process = new AntigravityProcess(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, json_encode(["home" => getenv("GEMINI_DIR"), "inherited" => getenv("AGY_INHERITED")])."\n");'],
            env: ['GEMINI_DIR' => '/accounts/one'],
        );

        $lines = iterator_to_array($process->lines(), false);

        self::assertSame(['home' => '/accounts/one', 'inherited' => 'kept'], json_decode($lines[0], true));
    }

    /**
     * A host that keeps identities apart has to be able to take one away, not
     * only to set one.
     */
    public function test_false_removes_an_inherited_variable(): void
    {
        putenv('GEMINI_API_KEY=inherited');
        $_SERVER['GEMINI_API_KEY'] = 'inherited';

        $process = new AntigravityProcess(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, json_encode(["key" => getenv("GEMINI_API_KEY")])."\n");'],
            env: ['GEMINI_API_KEY' => false],
        );

        $lines = iterator_to_array($process->lines(), false);

        self::assertSame(['key' => false], json_decode($lines[0], true));
    }

    public function test_the_turn_is_written_to_stdin(): void
    {
        $process = new AntigravityProcess(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, json_encode(["in" => trim(stream_get_contents(STDIN))])."\n");'],
        );
        $process->start('{"event":"user","message":{"content":"hello"}}'."\n");

        $lines = iterator_to_array($process->lines(), false);

        self::assertSame(
            ['in' => '{"event":"user","message":{"content":"hello"}}'],
            json_decode($lines[0], true),
        );
    }

    /**
     * The CLI does not flush on line boundaries, so a line can arrive in pieces
     * and two lines can arrive in one read.
     */
    public function test_fragmented_output_is_reassembled_into_whole_lines(): void
    {
        $script = 'fwrite(STDOUT, "{\"a\":1}"); usleep(50000); fwrite(STDOUT, "\n{\"b\":2}\n{\"c\":3}");';
        $process = new AntigravityProcess([PHP_BINARY, '-r', $script]);

        $lines = iterator_to_array($process->lines(), false);

        self::assertSame(['{"a":1}', '{"b":2}', '{"c":3}'], $lines);
    }

    public function test_a_line_without_a_trailing_newline_is_still_delivered(): void
    {
        $process = new AntigravityProcess([PHP_BINARY, '-r', 'fwrite(STDOUT, "{\"last\":true}");']);

        self::assertSame(['{"last":true}'], iterator_to_array($process->lines(), false));
    }

    /**
     * Antigravity logs heavily to stderr on healthy runs, so stderr alone must
     * never be read as failure.
     */
    public function test_stderr_is_collected_without_failing_the_run(): void
    {
        $process = new AntigravityProcess([
            PHP_BINARY, '-r',
            'fwrite(STDERR, "ERROR: logging before google.Init: noise\n"); fwrite(STDOUT, "{\"ok\":true}\n");',
        ]);

        $lines = $process->lines();
        $collected = iterator_to_array($lines, false);
        $result = $lines->getReturn();

        self::assertSame(['{"ok":true}'], $collected);
        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString('logging before google.Init', $result->stderr);
    }

    public function test_a_cancelled_run_stops_the_process(): void
    {
        $process = new AntigravityProcess([PHP_BINARY, '-r', 'while (true) { usleep(10000); }']);

        $this->expectException(ProcessCancelled::class);

        try {
            foreach ($process->lines(static fn (): bool => true) as $ignored) {
                // No output is expected before cancellation takes effect.
            }
        } finally {
            self::assertFalse($process->isRunning(), 'cancellation must not leave the child running');
        }
    }

    public function test_a_deadline_stops_the_process(): void
    {
        $process = new AntigravityProcess(
            [PHP_BINARY, '-r', 'while (true) { usleep(10000); }'],
            timeout: 0.2,
        );

        try {
            iterator_to_array($process->lines(), false);
            self::fail('the run should have timed out');
        } catch (ProcessTimedOut) {
            self::assertFalse($process->isRunning());
        }
    }

    /**
     * An abandoned iteration is the ordinary case when a caller stops early,
     * and it must not leave a child behind.
     */
    public function test_abandoning_the_iteration_stops_the_process(): void
    {
        $process = new AntigravityProcess([
            PHP_BINARY, '-r', 'fwrite(STDOUT, "{\"a\":1}\n"); while (true) { usleep(10000); }',
        ]);

        foreach ($process->lines() as $line) {
            self::assertSame('{"a":1}', $line);
            break;
        }
        unset($line);
        gc_collect_cycles();

        self::assertFalse($process->isRunning());
    }

    public function test_a_missing_binary_is_named(): void
    {
        $process = new AntigravityProcess(['agy-does-not-exist-anywhere']);

        $this->expectException(AntigravityNotFound::class);

        iterator_to_array($process->lines(), false);
    }

    public function test_a_failing_process_reports_its_exit_code(): void
    {
        $process = new AntigravityProcess([PHP_BINARY, '-r', 'exit(3);']);

        $lines = $process->lines();
        iterator_to_array($lines, false);

        self::assertSame(3, $lines->getReturn()->exitCode);
    }
}
