<?php

declare(strict_types=1);

namespace App\Chatbot\Infrastructure\SymfonyAI\Bridge\PythonWebSocket;

use Symfony\AI\Platform\Result\RawResultInterface;

final readonly class PythonWebSocketRawResult implements RawResultInterface
{
    public function __construct(
        private string $content,
        private array $metadata = [],
    ) {
    }

    public function getData(): array
    {
        return [
            'content' => $this->content,
            'metadata' => $this->metadata,
        ];
    }

    public function getObject(): object
    {
        return (object) $this->getData();
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}