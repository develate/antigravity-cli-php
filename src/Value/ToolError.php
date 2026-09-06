<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

final readonly class ToolError
{
    public function __construct(public string $type, public string $message)
    {
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $type = $data['type'] ?? '';
        $message = $data['message'] ?? '';

        return new self(
            is_string($type) ? $type : '',
            is_string($message) ? $message : '',
        );
    }
}
