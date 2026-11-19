# Changelog

## 1.3.0 - 2026-11-19

- Align the SDK with the published CLI 1.5.12 baseline: add Auto/Default modes and XHigh/Max effort, omit saved effort for newer fixed Gemini variants and documented Orchestrator slugs, and preserve unknown-model pass-through.
- Expose `finishedReason` on result events/results without changing status or exit handling; retain standalone report and quota exit codes, treating failed quota commands as unavailable.
- Clarify inherited defaults, permission bypass restrictions, sandbox requests, API-key/ADC authentication, plugin ZIP/URL validation, and persistent-process timeout semantics. Live CLI enforcement remains unverified.

## 1.2.0 - 2026-09-08

- Extend MCP registration with stdio and HTTP helpers, environment variables, HTTP headers, transport selection, and input validation while preserving existing `add()` calls.

## 1.1.0 - 2026-09-08

- Add process-free `ModelCapabilities::supportsReasoningEffort($slug)` and `ModelInfo::supportsReasoningEffort()` for composer choices. Omit unsupported effort flags for verified Claude models and explicit Gemini variants, including resumed sessions; preserve unknown/custom/default pass-through behavior.

## 1.0.0 - 2026-09-06

- Initial release: headless `agy` runs, persistent sessions, resume by conversation id, MCP and plugin management, and the `/usage` quota report.
