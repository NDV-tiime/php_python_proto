<?php
namespace LLMAgent;

use WebSocket\Client;
use Datto\JsonRpc\Server;
use Datto\JsonRpc\Evaluator;
use Exception;
use RuntimeException;

class FunctionEvaluator implements Evaluator
{
    private array $functions;

    public function __construct(array $functions)
    {
        $this->functions = $functions;
    }

    public function evaluate($method, $arguments)
    {
        if (!isset($this->functions[$method])) {
            throw new Exception("Method not found: $method", -32601);
        }

        $func = $this->functions[$method];
        if (!is_callable($func)) {
            throw new Exception("Method not callable: $method", -32601);
        }

        if ($arguments === null) {
            $arguments = [];
        }

        return call_user_func_array($func, $arguments);
    }
}

class AgentClient
{
    private string $wsUrl;
    private Server $server;
    private array $messages = [];
    private array $analysis_results = [];
    private ?string $agent_response = null;

    public function __construct(string $wsUrl = "ws://127.0.0.1:9000/ws")
    {
        $this->wsUrl = $wsUrl;
    }

    public function registerFunctions(array $functions): void
    {
        $evaluator = new FunctionEvaluator($functions);
        $this->server = new Server($evaluator);
    }

    public function sendMessage(string $message, string $userName = 'User'): array
    {
        $this->messages = [];
        $this->analysis_results = [];
        $this->agent_response = null;

        try {
            $client = new Client($this->wsUrl);

            // Send initial setup message with user data
            $setupMessage = [
                'type' => 'setup',
                'data' => [
                    'user_message' => $message,
                    'user_name' => $userName
                ]
            ];
            $client->send(json_encode($setupMessage));

            // Add setup message to log
            $this->messages[] = ['type' => 'sent', 'data' => $setupMessage];

            $timeout = 15;
            $startTime = time();

            while (time() - $startTime < $timeout) {
                try {
                    $response = $client->receive();

                    if ($response === false || $response === null) {
                        break;
                    }

                    if ($response) {
                        $data = json_decode($response, true);

                        if (!$data) {
                            continue;
                        }

                        // Handle agent response message
                        if (($data['type'] ?? null) === 'agent_response') {
                            $this->messages[] = ['type' => 'received', 'data' => $data];
                            $this->agent_response = $data['data']['response'] ?? 'No response from agent';
                            continue;
                        }

                        // Handle RPC calls
                        if (($data['type'] ?? null) !== 'rpc_call') {
                            continue;
                        }

                        $this->messages[] = ['type' => 'received', 'data' => $data];

                        $rpcRequest = $data['data'] ?? null;
                        $method = $rpcRequest['method'] ?? '';
                        $params = $rpcRequest['params'] ?? [];
                        $pythonReqId = $data['id'] ?? null;

                        try {
                            $replyJson = $this->server->reply(json_encode($rpcRequest));
                            $replyData = json_decode($replyJson, true);

                            // Check if we got a valid response
                            if ($replyData === null) {
                                // Create an error response if JSON-RPC failed
                                $replyData = [
                                    'jsonrpc' => '2.0',
                                    'id' => $rpcRequest['id'] ?? null,
                                    'error' => [
                                        'code' => -32603,
                                        'message' => 'Internal error: JSON-RPC processing failed'
                                    ]
                                ];
                            }

                            // Store analysis results
                            if (isset($replyData['result'])) {
                                $this->analysis_results[$method] = [
                                    'params' => $params,
                                    'result' => $replyData['result']
                                ];
                            }

                            $response = [
                                'type' => 'rpc_response',
                                'id' => $pythonReqId,
                                'data' => $replyData,
                            ];
                        } catch (Exception $rpcError) {
                            // Create an error response if there was an exception
                            $response = [
                                'type' => 'rpc_response',
                                'id' => $pythonReqId,
                                'data' => [
                                    'jsonrpc' => '2.0',
                                    'id' => $rpcRequest['id'] ?? null,
                                    'error' => [
                                        'code' => -32603,
                                        'message' => 'Internal error: ' . $rpcError->getMessage()
                                    ]
                                ]
                            ];
                        }

                        $this->messages[] = ['type' => 'sent', 'data' => $response];
                        $client->send(json_encode($response));
                    }
                } catch (Exception $e) {
                    break;
                }

                usleep(50000);
            }

        } catch (Exception $e) {
            if (!empty($this->messages)) {
                return [
                    'messages' => $this->messages,
                    'response' => $this->agent_response ?? 'Communication error with agent'
                ];
            }
            throw new RuntimeException("Failed to communicate with agent: " . $e->getMessage());
        }

        return [
            'messages' => $this->messages,
            'response' => $this->agent_response ?? 'No response received from agent'
        ];
    }

}