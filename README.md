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
- The `agy` binary on `PATH`, already signed in (`agy` manages its own Google
  credentials in the operating system keyring)

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

// Everything is auto-approved.
new SessionOptions(dangerouslyBypassPermissions: true);
```

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

`agy models` in CLI 1.1.27 returns only slug/label pairs, with no capability metadata.
`ModelCapabilities` centralizes the fallback rules, verified on that version using
`agy --print=/usage --output-format=json --model=SLUG --effort=LEVEL` (no model turn):

- `claude-opus-4-6-thinking` and `claude-sonnet-4-6` reject `--effort`.
- Explicit `gemini-…-low`, `-medium` and `-high` variants reject a conflicting
  effort and accept a matching one. They return false because their effort is
  already fixed by the slug.
- Base `gemini-3.8-flash` accepts `--effort=high`.

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

## Compatibility

```php
$agy->isAvailable();  // the binary exists and is executable
$agy->isCompatible(); // it answers a command this SDK relies on
$agy->version();      // null when it cannot be read
```

A binary whose version cannot be read is unknown, not incompatible. Compatibility
is decided by the interface being present.

## Environment and identity

The environment belongs to the client, not to a single call, so a listing and a
run cannot disagree about which configuration they are using. `false` removes an
inherited variable:

```php
$agy = new Antigravity('agy', env: [
    'GEMINI_DIR' => '/srv/accounts/one',
    'SOME_INHERITED_KEY' => false,
]);
```

Antigravity adopts the local configuration it finds. It has no identity-isolation
variable to set, and signing in and out is done through the CLI itself.

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
