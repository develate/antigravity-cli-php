# Changelog

## Unreleased

- Add process-free `ModelCapabilities::supportsReasoningEffort($slug)` and `ModelInfo::supportsReasoningEffort()` for composer choices. Omit unsupported effort flags for verified Claude models and explicit Gemini variants, including resumed sessions; preserve unknown/custom/default pass-through behavior.

## 1.0.0 - 2026-09-06

- Initial release: headless `agy` runs, persistent sessions, resume by conversation id, MCP and plugin management, and the `/usage` quota report.
