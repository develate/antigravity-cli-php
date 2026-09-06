<?php

declare(strict_types=1);

/**
 * A stand-in for `agy`, faithful to the parts of its interface this SDK uses:
 * the flag syntax, the NDJSON turn protocol, the cumulative result usage and
 * the diagnostic chatter on stderr.
 */

$arguments = array_slice($argv, 1);
$flags = [];
$positional = [];
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--')) {
        [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, null);
        $flags[$name] = $value ?? true;
    } else {
        $positional[] = $argument;
    }
}

fwrite(STDERR, "ERROR: logging before google.Init: I0906 server.go:1583] Language server version: 1.1.27\n");

if (isset($flags['version'])) {
    fwrite(STDOUT, "1.1.27\n");
    exit(0);
}

$command = $positional[0] ?? null;

if ($command === 'help') {
    fwrite(STDOUT, "Usage: agy ".($positional[1] ?? '')." [flags]\n");
    exit(0);
}

if ($command === 'models') {
    fwrite(STDOUT, "gemini-3.8-flash-low\tGemini 3.8 Flash (Low)\n");
    fwrite(STDOUT, "claude-sonnet-4-6\tClaude Sonnet 4.6 (Thinking)\n");
    exit(0);
}

if ($command === 'agents') {
    exit(0);
}

if ($command === 'mcp' && ($positional[1] ?? '') === 'list') {
    fwrite(STDOUT, "No MCP servers configured.\n");
    exit(0);
}

$print = $flags['print'] ?? null;

// A standalone report: answered by the CLI itself, with no conversation.
if (is_string($print) && str_starts_with($print, '/')) {
    if ($print === '/usage') {
        fwrite(STDOUT, "Gemini Models\tWeekly Limit Remaining\t97%\t2026-09-13T21:32:08Z\n");
        exit(0);
    }
    exit(0);
}

// A new conversation gets a fresh id, so its running totals start at zero the
// way a real new conversation's do.
$conversation = $flags['conversation'] ?? bin2hex(random_bytes(8));
$emit = static function (array $payload): void {
    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES)."\n");
};

$emit([
    'event' => 'init',
    'conversation_id' => $conversation,
    'init' => [
        'model' => $flags['model'] ?? 'gemini-3.8-flash-low',
        'cwd' => getcwd(),
        'tools' => ['run_command'],
        'permission_mode' => 'request-review',
    ],
]);

// Usage is cumulative across the conversation, and a conversation outlives the
// process that answered a turn of it, so the totals are kept on disk exactly as
// the real CLI keeps them in its trajectory store.
$stateFile = sys_get_temp_dir().'/antigravity-cli-php-tests/state-'.preg_replace('/[^a-z0-9-]/i', '', (string) $conversation).'.json';
$state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : null;
$state = is_array($state) ? $state : [];
$cumulativeInput = (int) ($state['input'] ?? 0);
$cumulativeOutput = (int) ($state['output'] ?? 0);
$turn = (int) ($state['turn'] ?? 0);
$step = (int) ($state['step'] ?? 0);

$persist = static function () use (&$cumulativeInput, &$cumulativeOutput, &$turn, &$step, $stateFile): void {
    file_put_contents($stateFile, json_encode([
        'input' => $cumulativeInput,
        'output' => $cumulativeOutput,
        'turn' => $turn,
        'step' => $step,
    ]));
};

$handle = ($flags['input-format'] ?? null) === 'stream-json' ? STDIN : null;

$runTurn = static function (string $prompt) use (&$cumulativeInput, &$cumulativeOutput, &$turn, &$step, $conversation, $emit, $persist): void {
    $turn++;
    $input = 1000 * $turn;
    $output = strlen($prompt);
    $cumulativeInput += $input;
    $cumulativeOutput += $output;

    $emit(['event' => 'step_update', 'step_update' => [
        'conversation_id' => $conversation,
        'step_index' => $step++,
        'state' => 'DONE',
        'step_type' => 'user_input',
    ]]);

    $index = $step++;
    $emit(['event' => 'step_update', 'step_update' => [
        'conversation_id' => $conversation,
        'step_index' => $index,
        'state' => 'ACTIVE',
        'step_type' => 'agent_response',
        'text_delta' => 'echo: ',
    ]]);
    $emit(['event' => 'step_update', 'step_update' => [
        'conversation_id' => $conversation,
        'step_index' => $index,
        'state' => 'DONE',
        'step_type' => 'agent_response',
        'text_delta' => $prompt,
        'duration_seconds' => 0.1,
        'usage' => [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'thinking_tokens' => 0,
            'cache_read_tokens' => 0,
            'total_tokens' => $input + $output,
        ],
    ]]);

    $emit(['event' => 'result', 'result' => [
        'conversation_id' => $conversation,
        'status' => 'SUCCESS',
        'response' => 'echo: '.$prompt,
        'duration_seconds' => 0.2,
        'num_turns' => $turn,
        'usage' => [
            'input_tokens' => $cumulativeInput,
            'output_tokens' => $cumulativeOutput,
            'thinking_tokens' => 0,
            'cache_read_tokens' => 0,
            'total_tokens' => $cumulativeInput + $cumulativeOutput,
        ],
    ]]);

    $persist();
};

if ($handle === null) {
    $runTurn(is_string($print) ? $print : '');
    exit(0);
}

while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $message = json_decode($line, true);
    if (!is_array($message) || ($message['event'] ?? null) !== 'user') {
        fwrite(STDERR, 'warning: ignoring unsupported stream input message event'."\n");
        continue;
    }
    $runTurn((string) ($message['message']['content'] ?? ''));
}

exit(0);
