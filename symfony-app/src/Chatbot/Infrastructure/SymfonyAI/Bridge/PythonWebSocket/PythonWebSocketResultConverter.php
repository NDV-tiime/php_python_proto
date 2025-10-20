<?php

declare(strict_types=1);

namespace App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket;

use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Model;

final class PythonWebSocketResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        // Check if this is our Python WebSocket model
        return $model->getName() === PythonWebSocketModel::PYTHON_AGENT_V1
            || str_starts_with($model->getName(), 'python-');
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if (!$result instanceof PythonWebSocketRawResult) {
            throw new \InvalidArgumentException('Expected PythonWebSocketRawResult');
        }

        // Create TextResult with content
        $textResult = new TextResult($result->getContent());

        // Store metadata in the result if needed
        // Note: TextResult doesn't have a metadata property, so we'll store it in raw result
        $textResult->setRawResult($result);

        return $textResult;
    }
}