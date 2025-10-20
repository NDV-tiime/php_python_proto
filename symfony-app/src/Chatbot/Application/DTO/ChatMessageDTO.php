<?php

declare(strict_types=1);

namespace App\Chatbot\Application\DTO;

final readonly class ChatMessageDTO
{
    public function __construct(
        public string $firstName,
        public string $message,
    ) {
    }
}
