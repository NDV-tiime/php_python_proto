<?php

declare(strict_types=1);

namespace App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket;

use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Model;

final class PythonWebSocketModelCatalog implements ModelCatalogInterface
{
    public function getModel(string $name): Model
    {
        return new PythonWebSocketModel($name);
    }

    /**
     * @return array<Model>
     */
    public function getModels(): array
    {
        return [
            new PythonWebSocketModel(PythonWebSocketModel::PYTHON_AGENT_V1),
        ];
    }
}
