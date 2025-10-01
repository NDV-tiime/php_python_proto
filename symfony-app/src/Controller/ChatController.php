<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use LLMAgent\AgentClient;

class ChatController extends AbstractController
{
    #[Route("/", name: "home")]
    public function index(): Response
    {
        return $this->render('chat/index.html.twig');
    }

    #[Route("/chat", name: "chat", methods: ["POST"])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $firstName = $data['firstName'] ?? '';
        $message = $data['message'] ?? '';

        if (empty($firstName)) {
            return new JsonResponse(['error' => 'First name required'], 400);
        }

        $functions = [
            'getStringLength' => function(string $text): int {
                return strlen($text);
            },
            'countWords' => function(string $text): int {
                return str_word_count($text);
            },
            'reverseString' => function(string $text): string {
                // Use mb_str_split to properly handle UTF-8 characters
                $chars = mb_str_split($text, 1, 'UTF-8');
                return implode('', array_reverse($chars));
            }
        ];

        try {
            $client = new AgentClient();
            $client->registerFunctions($functions);

            $result = $client->sendMessage($message, $firstName);

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
