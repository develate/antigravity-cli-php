<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

/** Model rules for `agy models`, which currently exposes no capability metadata. */
final class ModelCapabilities
{
    /**
     * Whether a separate reasoning effort can be selected for this slug.
     *
     * Pure and independent of model discovery. Unknown/custom slugs and
     * null/empty (the CLI default) remain permissive for compatibility.
     * This does not validate model availability or resolve the account default.
     *
     * Verified with agy 1.1.27: these exact Claude models reject --effort;
     * explicit Gemini variants accept only their already encoded effort.
     */
    public static function supportsReasoningEffort(?string $model = null): bool
    {
        if (in_array(strtolower($model ?? ''), [
            'claude-opus-4-6-thinking',
            'claude-sonnet-4-6',
        ], true)) {
            return false;
        }

        return preg_match('/^gemini-.+-(low|medium|high)$/i', $model ?? '') !== 1;
    }
}
