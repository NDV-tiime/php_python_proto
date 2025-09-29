<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../consumer-app/vendor/autoload.php';

use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Message\Message;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\PingResponder;
use Datto\JsonRpc\Server;
use Datto\JsonRpc\Evaluator;
use App\Service\ConsumerFunctions;

/**
 * Adaptateur qui implémente l'interface Evaluator de Datto\JsonRpc
 * et délègue les appels à l'objet ConsumerFunctions.
 */
class ConsumerEvaluator implements Evaluator
{
    private ConsumerFunctions $api;

    public function __construct(ConsumerFunctions $api)
    {
        $this->api = $api;
    }

    public function evaluate($method, $arguments)
    {
        if (!method_exists($this->api, $method)) {
            throw new \Exception("Méthode non trouvée: $method", -32601);
        }

        if ($arguments === null) {
            $arguments = [];
        }

        // Params nommés (array associatif)
        if (is_array($arguments) && !empty($arguments) && array_keys($arguments) !== range(0, count($arguments) - 1)) {
            $ref = new \ReflectionMethod($this->api, $method);
            $orderedArgs = [];
            foreach ($ref->getParameters() as $param) {
                $name = $param->getName();
                if (!array_key_exists($name, $arguments)) {
                    throw new \Exception("Paramètre manquant: $name", -32602);
                }
                $orderedArgs[] = $arguments[$name];
            }
            return $ref->invokeArgs($this->api, $orderedArgs);
        }

        return $this->api->{$method}(...$arguments);
    }
}

// --- Instanciation ---
$api = new ConsumerFunctions();
$evaluator = new ConsumerEvaluator($api);
$server = new Server($evaluator);

$wsUrl = "ws://127.0.0.1:9000/ws";
echo "PHP Bridge: Connecting to $wsUrl ...\n";

try {
    // Création du client WebSocket avec middlewares
    $client = new Client($wsUrl);
    $client
        ->addMiddleware(new CloseHandler())
        ->addMiddleware(new PingResponder())
        ->onText(function (Client $client, Connection $connection, Message $message) use ($server) {
            $msg = $message->getContent();
            $data = json_decode($msg, true);

            if (!$data || ($data['type'] ?? null) !== 'rpc_call') {
                echo "PHP Bridge: Ignoring message: $msg\n";
                return;
            }

        $rpcRequest = $data['data'] ?? null;
        $pythonReqId = $data['id'] ?? null;

        echo "PHP Bridge: RPC request from Python: " . json_encode($rpcRequest) . "\n";

        $replyJson = $server->reply(json_encode($rpcRequest));
        echo "PHP Bridge: RPC reply: $replyJson\n";

            $response = [
                'type' => 'rpc_response',
                'id'   => $pythonReqId,
                'data' => json_decode($replyJson, true),
            ];

            $client->text(json_encode($response));
        })
        ->onClose(function (Client $client, Connection $connection, Message $message) {
            $code = $message->getOpcode();
            $reason = $message->getContent();
            echo "PHP Bridge: Connection closed ({$code} - {$reason})\n";
        });

    echo "PHP Bridge: Connected to Python WebSocket.\n";
    
    $client->start();
    
} catch (\WebSocket\ConnectionException $e) {
    echo "PHP Bridge: Connection failed: {$e->getMessage()}\n";
    exit(1);
} catch (Exception $e) {
    echo "PHP Bridge: Error: {$e->getMessage()}\n";
    exit(1);
}
