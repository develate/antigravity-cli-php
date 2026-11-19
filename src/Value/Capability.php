<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

/**
 * What the Antigravity CLI can and cannot be asked to do through this SDK.
 *
 * The unsupported entries are unsupported because the CLI's headless interface
 * offers no way to express them, not because the SDK has not got to them yet.
 * Emulating them by inventing protocol messages is what this list exists to
 * prevent.
 */
enum Capability: string
{
    case OneShotRun = 'one_shot_run';
    case StreamingRun = 'streaming_run';
    case PersistentSession = 'persistent_session';
    case ResumeByConversationId = 'resume_by_conversation_id';
    case ModelSelection = 'model_selection';
    case AgentSelection = 'agent_selection';
    case ReasoningEffort = 'reasoning_effort';
    case StructuredOutput = 'structured_output';
    case AdditionalDirectories = 'additional_directories';
    /** The --sandbox flag is supported; active containment depends on the host. */
    case Sandbox = 'sandbox';
    case PermissionBypass = 'permission_bypass';
    case McpManagement = 'mcp_management';
    case PluginManagement = 'plugin_management';
    case RemoteControlDaemon = 'remote_control_daemon';
    case QuotaReport = 'quota_report';
    case Cancellation = 'cancellation';

    /**
     * Interactive-only: the CLI answers a permission prompt at its own
     * terminal, and headless runs deny instead of asking.
     */
    case InteractiveApproval = 'interactive_approval';
    /** No headless interface accepts image input. */
    case ImageInput = 'image_input';
    /** No headless interface forks or rewinds a conversation. */
    case ForkConversation = 'fork_conversation';
    case RewindConversation = 'rewind_conversation';
    /** Authentication is managed by the CLI itself, not through a headless command. */
    case ManagedAuthentication = 'managed_authentication';

    public function isSupported(): bool
    {
        return !in_array($this, self::unsupported(), true);
    }

    /** @return list<self> */
    public static function unsupported(): array
    {
        return [
            self::InteractiveApproval,
            self::ImageInput,
            self::ForkConversation,
            self::RewindConversation,
            self::ManagedAuthentication,
        ];
    }

    /** @return list<self> */
    public static function supported(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $capability): bool => $capability->isSupported(),
        ));
    }

    /**
     * Why this capability is unavailable, for an error a caller can act on.
     */
    public function reason(): ?string
    {
        return match ($this) {
            self::InteractiveApproval => 'Antigravity answers permission prompts at its own terminal. A headless run denies what it cannot approve and reports it as a denied action.',
            self::ImageInput => 'The Antigravity CLI accepts no image input in print mode.',
            self::ForkConversation => 'The Antigravity CLI cannot fork a conversation; only resuming an existing one by id is supported.',
            self::RewindConversation => 'The Antigravity CLI cannot rewind a conversation.',
            self::ManagedAuthentication => 'Antigravity signs in through its own CLI, storing Google credentials in the operating system keyring. There is no headless login or logout command to drive.',
            default => null,
        };
    }
}
