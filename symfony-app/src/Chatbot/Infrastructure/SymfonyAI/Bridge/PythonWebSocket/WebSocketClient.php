<?php

declare(strict_types=1);

namespace App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket;

use WebSocket\Client;
use WebSocket\Message\Text;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WebSocketClient
{
    private ?Client $client = null;

    public function __construct(
        #[Autowire(env: 'PYTHON_AGENT_URL')]
        private readonly string $url,
    ) {
    }

    public function connect(): void
    {
        if ($this->client !== null) {
            return;
        }

        try {
            $this->client = new Client($this->url);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to connect to WebSocket server: {$e->getMessage()}", 0, $e);
        }
    }

    public function send(string $data): void
    {
        if ($this->client === null) {
            throw new RuntimeException('WebSocket not connected');
        }

        // phrity/websocket v3 requires a Message object
        $message = new Text($data);
        $this->client->send($message);
    }

    public function receive(): string
    {
        if ($this->client === null) {
            throw new RuntimeException('WebSocket not connected');
        }

        $message = $this->client->receive();

        if ($message === null) {
            throw new RuntimeException('Failed to receive message from WebSocket');
        }

        // phrity/websocket v3 returns a Message object
        return $message->getContent();
    }

    public function close(): void
    {
        if ($this->client !== null) {
            $this->client->close();
            $this->client = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
