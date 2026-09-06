<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Event;

/**
 * The first event of a process: what the CLI actually resolved to run with.
 */
final readonly class InitEvent implements Event
{
    /**
     * @param list<string> $tools
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $conversationId,
        public ?string $model,
        public ?string $cwd,
        public array $tools,
        public ?string $permissionMode,
        private array $raw,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $init = $payload['init'] ?? [];
        $init = is_array($init) ? $init : [];

        $tools = [];
        foreach (is_array($init['tools'] ?? null) ? $init['tools'] : [] as $tool) {
            if (is_string($tool)) {
                $tools[] = $tool;
            }
        }

        return new self(
            conversationId: self::string($payload, 'conversation_id') ?? '',
            model: self::string($init, 'model'),
            cwd: self::string($init, 'cwd'),
            tools: $tools,
            permissionMode: self::string($init, 'permission_mode'),
            raw: $payload,
        );
    }

    public function name(): string
    {
        return 'init';
    }

    public function raw(): array
    {
        return $this->raw;
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
