<?php

declare(strict_types=1);

namespace App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket;

final class JsonRpcHandler
{
    /** @var array<string, callable> */
    private array $functions = [];

    /** @var array<int, array<string, mixed>> */
    private array $messages = [];

    /**
     * @param array<string, callable> $functions
     */
    public function registerFunctions(array $functions): void
    {
        $this->functions = $functions;
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>|null
     */
    public function handleMessage(array $message): ?array
    {
        // Store message in history
        $this->messages[] = $message;

        // Handle JSON-RPC request (function call from agent)
        if (isset($message['method']) && isset($message['id'])) {
            return $this->handleRpcRequest($message);
        }

        // Handle agent response
        if (isset($message['type']) && $message['type'] === 'response') {
            return null; // Final response, no reply needed
        }

        return null;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function handleRpcRequest(array $request): array
    {
        $method = $request['method'];
        $params = $request['params'] ?? [];
        $id = $request['id'];

        if (!isset($this->functions[$method])) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32601,
                    'message' => "Method not found: {$method}",
                ],
            ];
        }

        try {
            $result = call_user_func_array($this->functions[$method], $params);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function clearMessages(): void
    {
        $this->messages = [];
    }
}
