<?php

declare(strict_types=1);

namespace App\Chatbot\Application\Handler;

use App\Chatbot\Application\DTO\ChatMessageDTO;
use App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket\PythonWebSocketModel;
use Symfony\AI\Platform\PlatformInterface;

final readonly class SendMessageHandler
{
    public function __construct(
        private PlatformInterface $platform,
    ) {
    }

    /**
     * @param array<string, callable> $functions
     * @param array<string, array{description: string, parameters: array<string, mixed>}> $functionMetadata
     * @return array{response: string, messages: array<int, array<string, mixed>>}
     */
    public function handle(ChatMessageDTO $dto, array $functions, array $functionMetadata): array
    {
        // Prepare options for the platform - pass functions in options
        $options = [
            'firstName' => $dto->firstName,
            'availableFunctions' => $functionMetadata,
            'functions' => $functions, // Functions will be handled by ModelClient
        ];

        // Invoke the platform with model name as string
        $deferredResult = $this->platform->invoke(PythonWebSocketModel::PYTHON_AGENT_V1, $dto->message, $options);

        // Get the result from deferred
        $result = $deferredResult->getResult();

        // Get metadata from raw result
        $rawResult = $result->getRawResult();
        $metadata = $rawResult ? $rawResult->getData()['metadata'] ?? [] : [];

        return [
            'response' => $result->getContent(),
            'messages' => $metadata['messages'] ?? [],
        ];
    }
}
