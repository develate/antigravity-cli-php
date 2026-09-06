<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Event;

use Develate\AntigravityCli\Exception\InvalidStreamJson;

final class EventParser
{
    /**
     * @param bool $strict whether a line that is not an event object is fatal
     */
    public function __construct(private readonly bool $strict = false)
    {
    }

    /**
     * @return list<Event> the events on this line; empty when the line carries none
     *
     * @throws InvalidStreamJson in strict mode, when the line is not a JSON object
     */
    public function parseLine(string $line): array
    {
        $line = trim($line);
        if ($line === '') {
            return [];
        }

        try {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            if ($this->strict) {
                throw new InvalidStreamJson($line, $exception);
            }

            return [];
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            if ($this->strict) {
                throw new InvalidStreamJson($line);
            }

            return [];
        }

        return [$this->parseArray($decoded)];
    }

    /** @param array<string, mixed> $payload */
    public function parseArray(array $payload): Event
    {
        $name = $payload['event'] ?? null;
        $name = is_string($name) ? $name : '';

        return match ($name) {
            'init' => InitEvent::fromArray($payload),
            'step_update' => StepUpdateEvent::fromArray($payload),
            'result' => ResultEvent::fromArray($payload),
            'command_result' => CommandResultEvent::fromArray($payload),
            default => new UnknownEvent($name, $payload),
        };
    }
}
