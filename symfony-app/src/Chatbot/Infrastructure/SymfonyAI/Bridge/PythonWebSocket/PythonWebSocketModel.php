<?php

declare(strict_types=1);

namespace App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket;

use Symfony\AI\Platform\Model;

final class PythonWebSocketModel extends Model
{
    public const PYTHON_AGENT_V1 = 'python-agent-v1';
    public const TYPE = 'python-websocket';

    public function __construct(string $name = self::PYTHON_AGENT_V1)
    {
        // Model constructor expects: name, capabilities array, (optionally) type
        parent::__construct($name, ['chat']);
    }
}
