<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use PhpPython\AgentClient;

class ChatController extends AbstractController
{
    #[Route("/", name: "home")]
    public function index(): Response
    {
        return $this->render('chat/index.html.twig');
    }

    public function getStringLength(string $text): int
    {
        return strlen($text);
    }

    public function countWords(string $text): int
    {
        return str_word_count($text);
    }

    public function reverseString(string $text): string
    {
        $chars = mb_str_split($text, 1, 'UTF-8');
        return implode('', array_reverse($chars));
    }

    private function getAvailableFunctions(): array
    {
        return [
            'getStringLength' => [
                'function' => [$this, 'getStringLength'],
                'description' => 'Get the length of a text string',
                'parameters' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text to measure'
                    ]
                ]
            ],
            'countWords' => [
                'function' => [$this, 'countWords'],
                'description' => 'Count the number of words in a text',
                'parameters' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text to analyze'
                    ]
                ]
            ],
            'reverseString' => [
                'function' => [$this, 'reverseString'],
                'description' => 'Reverse a text string (supports UTF-8 characters)',
                'parameters' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text to reverse'
                    ]
                ]
            ]
        ];
    }

    private function getFunctionMetadata(): array
    {
        $functions = $this->getAvailableFunctions();
        $metadata = [];

        foreach ($functions as $name => $config) {
            $metadata[$name] = [
                'description' => $config['description'],
                'parameters' => $config['parameters']
            ];
        }

        return $metadata;
    }

    #[Route("/chat", name: "chat", methods: ["POST"])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $firstName = $data['firstName'] ?? '';
        $message = $data['message'] ?? '';

        if (empty($firstName)) {
            return new JsonResponse(['error' => 'Name required'], 400);
        }

        try {
            $availableFunctions = $this->getAvailableFunctions();
            $functionMetadata = $this->getFunctionMetadata();

            // Extract just the callable functions for registration
            $callableFunctions = [];
            foreach ($availableFunctions as $name => $config) {
                $callableFunctions[$name] = $config['function'];
            }

            $client = new AgentClient();
            $client->registerFunctions($callableFunctions);

            // Send both the message and the function metadata to the agent
            $result = $client->sendMessage($message, $firstName, $functionMetadata);

            return new JsonResponse([
                'success' => true,
                'messages' => $result['messages'],
                'response' => $result['response']
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to communicate with agent: ' . $e->getMessage()
            ], 500);
        }
    }
}
