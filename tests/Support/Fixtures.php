<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Support;

/**
 * Lines copied from real `agy --output-format=stream-json` output.
 */
final class Fixtures
{
    public const CONVERSATION = 'eb0f0e55-72c1-45a1-a578-4d54c5d0b1f0';

    /** @return list<string> */
    public static function simpleTurn(string $conversation = self::CONVERSATION): array
    {
        return [
            self::init($conversation),
            self::step($conversation, 0, 'DONE', 'user_input'),
            '{"event":"step_update","step_update":{"conversation_id":"'.$conversation.'","step_index":1,"state":"ACTIVE","step_type":"agent_response","text_delta":"PONG."}}',
            '{"event":"step_update","step_update":{"conversation_id":"'.$conversation.'","step_index":1,"state":"DONE","step_type":"agent_response","text_delta":"\n","duration_seconds":1.34,"usage":{"input_tokens":5087,"output_tokens":3,"thinking_tokens":0,"cache_read_tokens":8128,"total_tokens":5090}}}',
            '{"event":"result","result":{"conversation_id":"'.$conversation.'","status":"SUCCESS","response":"PONG.\n","duration_seconds":1.38,"num_turns":1,"usage":{"input_tokens":5087,"output_tokens":3,"thinking_tokens":0,"cache_read_tokens":8128,"total_tokens":5090}}}',
        ];
    }

    /**
     * A second turn on the same conversation: its `result` usage is the sum of
     * both turns, exactly as the CLI reports it.
     *
     * @return list<string>
     */
    public static function resumedTurn(string $conversation = self::CONVERSATION): array
    {
        return [
            self::init($conversation),
            self::step($conversation, 2, 'DONE', 'user_input'),
            '{"event":"step_update","step_update":{"conversation_id":"'.$conversation.'","step_index":4,"state":"DONE","step_type":"agent_response","text_delta":"You asked me to say PONG.","duration_seconds":3.88,"usage":{"input_tokens":13433,"output_tokens":9,"thinking_tokens":0,"cache_read_tokens":0,"total_tokens":13442}}}',
            '{"event":"result","result":{"conversation_id":"'.$conversation.'","status":"SUCCESS","response":"You asked me to say PONG.","duration_seconds":33.6,"num_turns":2,"usage":{"input_tokens":18520,"output_tokens":12,"thinking_tokens":0,"cache_read_tokens":8128,"total_tokens":18532}}}',
        ];
    }

    /** @return list<string> a turn whose command was denied by the sandbox */
    public static function deniedTurn(string $conversation = self::CONVERSATION): array
    {
        return [
            self::init($conversation),
            '{"event":"step_update","step_update":{"conversation_id":"'.$conversation.'","step_index":4,"state":"ACTIVE","step_type":"tool","tool_name":"run_command","tool_info":{"name":"run_command","parameters":{"CommandLine":"pwd && ls -la"}}}}',
            '{"event":"step_update","step_update":{"conversation_id":"'.$conversation.'","step_index":4,"state":"ERROR","step_type":"tool","tool_name":"run_command","duration_seconds":0.07,"tool_info":{"name":"run_command","parameters":{"CommandLine":"pwd && ls -la"},"error":{"type":"TOOL_ERROR","message":"user denied permission to run command"}}}}',
            '{"event":"result","result":{"conversation_id":"'.$conversation.'","status":"SUCCESS","response":"","duration_seconds":2.48,"num_turns":1,"usage":{"input_tokens":28055,"output_tokens":134,"thinking_tokens":0,"cache_read_tokens":0,"total_tokens":28189},"denied_actions":[{"action":"command","display_name":"RunCommand"}]}}',
        ];
    }

    public static function init(string $conversation = self::CONVERSATION): string
    {
        return '{"event":"init","conversation_id":"'.$conversation.'","init":{"model":"gemini-3.8-flash-low","cwd":"/tmp/p","tools":["run_command","view_file"],"permission_mode":"request-review"}}';
    }

    public static function step(string $conversation, int $index, string $state, string $type): string
    {
        return '{"event":"step_update","step_update":{"conversation_id":"'.$conversation.'","step_index":'.$index.',"state":"'.$state.'","step_type":"'.$type.'"}}';
    }
}
