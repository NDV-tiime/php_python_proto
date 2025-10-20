<?php

declare(strict_types=1);

namespace App\Chatbot\Domain\Service;

/**
 * Registry for AI agent callable functions.
 * Manages available functions and their metadata for the AI agent.
 */
final readonly class FunctionRegistryService
{
    public function __construct(
        private StringManipulationService $stringManipulation,
    ) {
    }

    /**
     * Get all available functions with their callables and metadata.
     *
     * @return array<string, array{function: callable, description: string, parameters: array<string, mixed>}>
     */
    public function getAvailableFunctions(): array
    {
        return [
            'getStringLength' => [
                'function' => $this->stringManipulation->getStringLength(...),
                'description' => 'Get the length of a text string',
                'parameters' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text to measure',
                    ],
                ],
            ],
            'countWords' => [
                'function' => $this->stringManipulation->countWords(...),
                'description' => 'Count the number of words in a text',
                'parameters' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text to analyze',
                    ],
                ],
            ],
            'reverseString' => [
                'function' => $this->stringManipulation->reverseString(...),
                'description' => 'Reverse a text string (supports UTF-8 characters)',
                'parameters' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text to reverse',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get only the callable functions for registration.
     *
     * @return array<string, callable>
     */
    public function getCallableFunctions(): array
    {
        $callableFunctions = [];
        foreach ($this->getAvailableFunctions() as $name => $config) {
            $callableFunctions[$name] = $config['function'];
        }
        return $callableFunctions;
    }

    /**
     * Get function metadata for the AI agent.
     *
     * @return array<string, array{description: string, parameters: array<string, mixed>}>
     */
    public function getFunctionMetadata(): array
    {
        $metadata = [];
        foreach ($this->getAvailableFunctions() as $name => $config) {
            $metadata[$name] = [
                'description' => $config['description'],
                'parameters' => $config['parameters'],
            ];
        }
        return $metadata;
    }
}