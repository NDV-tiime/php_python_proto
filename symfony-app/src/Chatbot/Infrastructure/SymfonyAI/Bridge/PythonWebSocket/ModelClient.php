<?php

declare(strict_types=1);

namespace App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Message\MessageBag;
use RuntimeException;

final class ModelClient implements ModelClientInterface
{
    private string $websocketUrl;
    private JsonRpcHandler $rpcHandler;

    public function __construct(
        string $websocketUrl = 'ws://localhost:9000/ws'
    ) {
        $this->websocketUrl = $websocketUrl;
        $this->rpcHandler = new JsonRpcHandler();
    }

    public function supports(Model $model): bool
    {
        // Check if this is our Python WebSocket model
        return $model->getName() === PythonWebSocketModel::PYTHON_AGENT_V1
            || str_starts_with($model->getName(), 'python-');
    }

    public function request(Model $model, $input, array $options = []): RawResultInterface
    {
        // Register functions if provided in options
        if (isset($options['functions'])) {
            $this->rpcHandler->registerFunctions($options['functions']);
        }

        try {
            // Create and connect WebSocket client
            $client = new WebSocketClient($this->websocketUrl);
            $client->connect();

            // Clear previous messages
            $this->rpcHandler->clearMessages();

            // Create setup message
            $setupMessage = $this->createSetupMessage($input, $options);

            // Send setup message
            $client->send(json_encode($setupMessage, JSON_THROW_ON_ERROR));

            // Message processing loop
            $finalResponse = '';
            while (true) {
                $rawMessage = $client->receive();
                $messageData = json_decode($rawMessage, true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($messageData)) {
                    throw new RuntimeException('Invalid message format received from agent');
                }

                $messageType = $messageData['type'] ?? null;

                if ($messageType === 'rpc_call') {
                    // Handle RPC call from Python agent
                    $rpcRequest = $messageData['data'] ?? [];
                    $requestId = $messageData['id'] ?? null;

                    $rpcReply = $this->handleRpcRequest($rpcRequest);

                    // Format and send reply
                    $wsReply = [
                        'type' => 'rpc_response',
                        'id' => $requestId,
                        'data' => $rpcReply,
                    ];
                    $client->send(json_encode($wsReply, JSON_THROW_ON_ERROR));
                    continue;
                }

                // Check for final agent response
                if ($messageType === 'agent_response') {
                    $finalResponse = $messageData['data']['response'] ?? '';
                    break;
                }
            }

            $client->close();

            // Create raw result with metadata
            $metadata = ['messages' => $this->rpcHandler->getMessages()];
            return new PythonWebSocketRawResult($finalResponse, $metadata);

        } catch (\Throwable $e) {
            if (isset($client)) {
                $client->close();
            }
            throw new RuntimeException("Failed to communicate with Python agent: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create setup message for WebSocket.
     */
    private function createSetupMessage($input, array $options): array
    {
        // Extract message content
        $message = $this->extractMessage($input);

        // Get user options
        $firstName = $options['firstName'] ?? 'User';
        $availableFunctions = $options['availableFunctions'] ?? [];

        // Create setup message matching Python agent's expected format
        return [
            'type' => 'setup',
            'data' => [
                'user_message' => $message,
                'user_name' => $firstName,
                'available_functions' => $availableFunctions,
            ],
        ];
    }

    /**
     * Extract message content from various input types.
     */
    private function extractMessage($input): string
    {
        if ($input instanceof MessageBag) {
            $messages = [];
            foreach ($input->getMessages() as $message) {
                $messages[] = $message->getContent();
            }
            return implode("\n", $messages);
        }

        if (is_string($input)) {
            return $input;
        }

        if (is_array($input) || is_object($input)) {
            return json_encode($input);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function handleRpcRequest(array $request): array
    {
        $method = $request['method'] ?? null;
        $id = $request['id'] ?? null;

        if ($method === null) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: missing method',
                ],
            ];
        }

        $reply = $this->rpcHandler->handleMessage($request);

        return $reply ?? [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => null,
        ];
    }
}
