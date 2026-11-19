# antigravity-cli-php

A PHP SDK for driving the [Antigravity](https://antigravity.google) CLI (`agy`) headlessly.

It speaks the CLI's documented print-mode interface and nothing else: no private
endpoint, no undocumented protocol, and no shared-configuration workaround. What
the CLI cannot be asked to do, this SDK reports as unsupported rather than
approximating.

```php
use Develate\AntigravityCli\Antigravity;

$agy = new Antigravity();

echo $agy->query('Summarise this repository.')->text;
```

## Requirements

- PHP 8.2+
- The `agy` binary on `PATH`, authenticated through CLI sign-in, an API key,
  or CLI Application Default Credentials (see Environment and identity below)

## Installation

```bash
composer require develate/antigravity-cli-php
```

## Runs

A run is one turn. `query()` waits for the answer; `stream()` hands back a `Run`
you can iterate as it happens.

```php
use Develate\AntigravityCli\Antigravity;
use Develate\AntigravityCli\RunOptions;
use Develate\AntigravityCli\SessionOptions;
use Develate\AntigravityCli\Event\StepUpdateEvent;
use Develate\AntigravityCli\Value\Effort;

$agy = (new Antigravity('agy'))->in('/srv/project');

$options = new SessionOptions(
    model: 'gemini-3.1-pro',
    effort: Effort::High,
);

$run = $agy->session($options)->stream('Explain the build pipeline.', new RunOptions(timeout: 300.0));

foreach ($run as $event) {
    if ($event instanceof StepUpdateEvent && $event->textDelta !== null) {
        echo $event->textDelta;
    }
}

$result = $run->result();
```

`Result` carries the answer text, the turn's status, its usage, the tool calls
it made, any actions the CLI's permission rules refused, and every event
verbatim.

`Result::$finishedReason` exposes the CLI's `finished_reason`, including unknown
future strings; it is `null` when absent. For example, `print_timeout` can
accompany a partial answer. Inspect it alongside `status` and `exitCode`; the SDK
does not infer a different status or exit code from this field.

`RunOptions::$printTimeout` is the CLI's turn limit. `RunOptions::$timeout` is
the SDK's process deadline and throws `ProcessTimedOut`, potentially before a
result arrives. Allow time for the CLI's retries/thinking and post-result
shutdown (bounded to 30 seconds in CLI 1.5.5+). Process-per-turn streams drain
until process exit; persistent streams end each turn at `result`.

### Cancelling

`Run::cancel()` stops the process at the next poll. The child is stopped when a
run is cancelled, throws, or is simply abandoned mid-iteration.

```php
$run->cancel();
```

## Conversations

Antigravity allocates the conversation id; this SDK never invents one. It
appears on the result and on the session once the CLI has answered.

```php
$session = $agy->session($options);
$session->query('Add a health check endpoint.');

$conversationId = $session->id();
```

Resuming needs that id explicitly:

```php
$resumed = $agy->resume($conversationId, $options);
$resumed->query('Now add a test for it.');
```

`agy --continue` resumes whatever conversation the CLI touched last, which on a
shared machine is not necessarily yours. The SDK does not offer it.

### One process per turn, or one per conversation

`session()` starts a process per turn, which is what you want when turns are
queued independently and cancelled on their own. `persistentSession()` keeps one
process for the whole conversation, which is cheaper per turn:

```php
$session = $agy->persistentSession($options);
$session->query('One');
$session->query('Two');
$session->close();
```

Turns run one at a time either way. A second turn started while the first is in
flight throws, because two answers on one pipe cannot be told apart.

A persistent process takes its flags and SDK deadline from its first turn.
Later turns do not reset that deadline or apply changed `printTimeout`/schema
flags. Use process-per-turn sessions when these options need to vary.

## Token accounting

The CLI's `result` event reports usage for the **whole conversation**, not the
turn. A second turn that reported it as its own would charge the first turn
twice.

`Result::$usage` is therefore built from the steps that completed during the
turn, and `Result::$cumulativeUsage` carries the CLI's running total unchanged:

```php
$result->usage?->inputTokens;           // this turn
$result->cumulativeUsage?->inputTokens; // the conversation so far
```

When neither the steps nor a trustworthy baseline can supply a figure, `$usage`
is `null`. Unknown is reported as unknown.

## Permissions

Antigravity's own permission rules apply, and they keep their native meaning.

```php
use Develate\AntigravityCli\Value\ExecutionMode;

// Edits are accepted; commands needing approval are denied, not prompted.
new SessionOptions(mode: ExecutionMode::AcceptEdits, sandbox: true);

// Skip ordinary approvals; protected-command and administrator restrictions remain.
new SessionOptions(dangerouslyBypassPermissions: true);
```

The default `mode: null` omits `--mode` and inherits the CLI's settings and
defaults. Unsaved mode defaults to Auto where available in CLI 1.5.7+, subject
to account, platform and sandbox restrictions. Use `ExecutionMode::Default` or
`ExecutionMode::Auto` to request either explicitly. Auto falls back to Default
when unavailable; the SDK does not attest the mode actually selected.

`sandbox: true` requests `--sandbox`. In CLI 1.4.4+ it has no effect when the
host cannot provide a sandbox, and CLI permissions can allow commands outside
the sandbox. `Capability::Sandbox` reports flag support only. Neither that
capability nor the init event's permission mode proves active containment.

A headless run cannot answer a permission prompt, so a sandboxed run denies what
it cannot approve and says so on the result:

```php
if ($result->hasDenials()) {
    foreach ($result->deniedActions as $denied) {
        echo $denied->displayName;
    }
}
```

A turn can therefore succeed having quietly skipped work you asked for. Check
`hasDenials()` when that matters.

`ExecutionMode::Plan` is Antigravity's own planning mode. It prefixes the
conversation with a planning instruction; it is not a read-only enforcement
boundary, and this SDK does not present it as one.

## Capabilities

Some things the CLI's headless interface simply does not offer. They are named
rather than emulated:

```php
use Develate\AntigravityCli\Value\Capability;

$agy->supports(Capability::ResumeByConversationId); // true
$agy->supports(Capability::ImageInput);             // false

$agy->capabilities()->require(Capability::InteractiveApproval); // throws UnsupportedCapability
```

Unsupported: interactive approval, image input, forking, rewinding, and
managed authentication.

### Model-specific reasoning effort

Use the pure helper for a selected slug, including a custom model or one that has
not been fetched. It does not instantiate a client or start a process:

```php
use Develate\AntigravityCli\Value\ModelCapabilities;

$showReasoningDropdown = ModelCapabilities::supportsReasoningEffort($selectedModel);
// false for 'claude-opus-4-6-thinking' or 'gemini-3.8-flash-high'
// true for 'gemini-3.8-flash', unknown/custom slugs, null and ''
```

Models returned by `$agy->models()` also expose `$model->supportsReasoningEffort()`.
The helper means **separately adjustable effort**, not whether the model reasons.

`Effort` includes `Low`, `Medium`, `High`, `XHigh` and `Max`. The CLI validates
which tiers the selected model offers; the enum does not promise all five for
every model. Neon and Hydrogen base models support XHigh. See the official
[effort documentation](https://antigravity.google/docs/cli/commands/effort/) and
[model documentation](https://antigravity.google/docs/models).

`agy models` in CLI 1.1.27 returns only slug/label pairs, with no capability metadata.
`ModelCapabilities` centralizes the original fallback rules, verified on that version using
`agy --print=/usage --output-format=json --model=SLUG --effort=LEVEL` (no model turn):

- `claude-opus-4-6-thinking` and `claude-sonnet-4-6` reject `--effort`.
- Explicit `gemini-…-low`, `-medium` and `-high` variants reject a conflicting
  effort and accept a matching one. They return false because their effort is
  already fixed by the slug.
- Base `gemini-3.8-flash` accepts `--effort=high`.

The 1.5.12 documentation adds fixed-budget Orchestrator and newer explicit
effort variants. The helper also excludes `gemini-pro-orchestrator`,
`gemini-pro-orchestrator-argon`, `gemini-pro-orchestrator-neon`, and Gemini
variants ending in `-xhigh` or `-max`. These additions follow the documented
fixed-budget/encoded-effort rules; they have not been live-probed by this suite.
Use `$agy->report('/effort')` to inspect the configured model without a model
turn. This standalone command is read-only and accepts no effort argument.

Matching is case-insensitive. Other Claude or “thinking” names are not excluded.
Unknown/custom names return true to preserve pass-through behavior; this is not a
guarantee that the CLI recognizes a model. Null and empty selections also return
true: the SDK cannot resolve the account's default without a process, so an
explicitly selected effort is still sent and the CLI validates it.

Command construction uses this same helper to omit `--effort` for unsupported
models, including saved effort values on resumed sessions. The selected model,
other options, and the immutable session options are preserved. Null effort always
omits the flag, for every model.

## Reports and commands

`/usage` is answered by the CLI itself, without asking a model:

```php
$quota = $agy->quota();

if ($quota->available) {
    foreach ($quota->entries as $entry) {
        echo "{$entry->group}: {$entry->remainingPercent}% until {$entry->resetsAt?->format('c')}\n";
    }
}
```

When the output is not recognised, `available` is `false` and the raw text and
stderr are kept. No percentage, reset time or account detail is ever invented.
`PrintReport::$exitCode` and `QuotaReport::$exitCode` retain the standalone
command's exit code. A nonzero exit makes quota unavailable even if stdout
resembles a quota row. In CLI 1.5.11+, API-key quota commands exit 2; this does
not establish that the caller is signed out.

The supported command families are wrapped too. Each one runs only when you call
it; none is a side effect of a run:

```php
$agy->models();
$agy->agents();
$agy->mcp()->list();
$agy->plugins()->list();
$agy->remoteControl()->status();
$agy->changelog();
$agy->update();   // updates the binary in place
$agy->install();  // writes to the shell profile
```

`mcp()` and `plugins()` edit configuration shared by every Antigravity session on
the machine.

Plugin validation accepts a directory, ZIP archive or GitHub URL (CLI 1.5.11+):

```php
$agy->plugins()->validate('/srv/plugins/example.zip');
$agy->plugins()->validate('https://github.com/example/plugin');
```

Remote control wraps daemon subcommands only. It does not expose the
`--remote-agent` startup flags restricted for API-key authentication in 1.5.12;
daemon subcommand behavior with API keys has not been verified here.

### MCP servers

Register local stdio servers with arguments and environment variables, or remote
HTTP servers with request headers:

```php
$agy->mcp()->addStdio('filesystem', 'npx', [
    '-y', '@modelcontextprotocol/server-filesystem', '/srv/project',
]);
$agy->mcp()->addStdio('tools', 'my-mcp-server', env: ['API_KEY' => $apiKey]);
$agy->mcp()->addHttp('api', 'https://example.com/mcp', headers: [
    'Authorization' => 'Bearer '.$token,
]);

$agy->mcp()->list(); // configured server names
$agy->mcp()->disable('api');
$agy->mcp()->enable('api');
$agy->mcp()->remove('api');
```

Adding an existing name updates its configuration. Mutation methods return
`CommandOutput` and throw `ProcessFailed` when the CLI exits unsuccessfully.
The existing `add('name', ['command', 'argument'])` API also accepts named
`env`, `headers`, and `type` (`stdio` or `http`) arguments. Values are passed as
individual process arguments, without shell interpolation.

These operations use the CLI's shared user configuration. Configure servers
before starting sessions; the SDK does not inject temporary MCP settings into
individual turns. Credentials supplied here are persisted by the CLI along with
the server configuration. This API follows `agy mcp add --help` in CLI 1.1.27
and the [official MCP command release notes](https://antigravity.google/changelog).

## Compatibility

```php
$agy->isAvailable();  // the binary exists and is executable
$agy->isCompatible(); // it answers a command this SDK relies on
$agy->version();      // null when it cannot be read
```

A binary whose version cannot be read is unknown, not incompatible. Compatibility
is decided by the interface being present.

Published stable coverage baseline: **CLI 1.5.12 (2026-11-19)**, based on the
release report and [official changelog](https://antigravity.google/changelog).
Installer rollout may lag the published release. Automated tests exercise a
stand-in CLI and recorded events; they do not certify live 1.5.12 enforcement,
retry timing, sandbox availability, or token accounting. No minimum CLI version
has been raised by this baseline.

## Environment and identity

The environment belongs to the client, not to a single call, so a listing and a
run cannot disagree about which configuration they are using. `false` removes an
inherited variable:

```php
$agy = new Antigravity('agy', env: [
    'GEMINI_API_KEY' => $apiKey,
    'SOME_INHERITED_KEY' => false,
]);
```

Antigravity adopts the local configuration it finds. It has no identity-isolation
variable to set, and signing in and out is done through the CLI itself.

API-key authentication also requires `modelProvider: gemini` in the CLI's own
settings; `GEMINI_API_KEY` alone is insufficient. CLI ADC uses
`AGY_ADC_AUTH=true`. The SDK forwards these environment values to both commands
and transports; it does not configure authentication or isolate identities.
See the official [authentication documentation](https://antigravity.google/docs/install/#using-a-gemini-api-key).

## Errors

| Exception | Meaning |
| --- | --- |
| `AntigravityNotFound` | the binary is missing or not executable |
| `ProcessFailed` | the process ended without answering the turn |
| `ProcessTimedOut` | the deadline passed |
| `ProcessCancelled` | the run was cancelled |
| `RunFailed` | the turn itself ended unsuccessfully |
| `InvalidOptions` | options the CLI cannot honour, rejected before starting |
| `UnsupportedCapability` | asked for something the CLI does not offer |
| `InvalidStreamJson` | malformed output, in strict parsing mode |

An unsuccessful turn is reported on the result rather than thrown, because the
CLI signals it in the `result` event and can still exit `0`. Use
`Run::resultOrFail()` when you would rather have an exception.

Antigravity writes a lot of diagnostic chatter to stderr on healthy runs, so
stderr alone is never read as failure.

## Testing

```bash
composer test
```

The suite runs against a stand-in binary that speaks the real flag and NDJSON
protocol, so no network access or Antigravity account is needed.

## License

MIT
