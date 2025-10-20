<?php

declare(strict_types=1);

namespace App\Chatbot\Domain\Service;

/**
 * Service for string manipulation operations.
 * Provides various text processing functions that can be called by the AI agent.
 */
final class StringManipulationService
{
    /**
     * Get the length of a text string.
     */
    public function getStringLength(string $text): int
    {
        return strlen($text);
    }

    /**
     * Count the number of words in a text.
     */
    public function countWords(string $text): int
    {
        return str_word_count($text);
    }

    /**
     * Reverse a text string with proper UTF-8 support.
     */
    public function reverseString(string $text): string
    {
        $chars = mb_str_split($text, 1, 'UTF-8');
        return implode('', array_reverse($chars));
    }
}