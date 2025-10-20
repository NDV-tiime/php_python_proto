<?php

declare(strict_types=1);

namespace App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket;

use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PlatformFactory
{
    /**
     * Create a new PythonWebSocket platform instance.
     *
     * @param string $websocketUrl The WebSocket URL for the Python agent
     * @return PlatformInterface
     */
    public static function create(
        #[Autowire(env: 'PYTHON_AGENT_URL')]
        string $websocketUrl,
    ): PlatformInterface {
        dump($websocketUrl);
        // Create the model client
        $modelClient = new ModelClient($websocketUrl);

        // Create the model catalog
        $modelCatalog = new PythonWebSocketModelCatalog();

        // Create the result converter
        $resultConverter = new PythonWebSocketResultConverter();

        // Create and configure the platform
        // Based on Ollama example, Platform constructor accepts arrays
        $platform = new Platform(
            [$modelClient],
            [$resultConverter],
            $modelCatalog
        );

        return $platform;
    }
}
