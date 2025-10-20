<?php

declare(strict_types=1);

namespace App\UserInterface\Controller;

use App\Chatbot\Application\DTO\ChatMessageDTO;
use App\Chatbot\Application\Handler\SendMessageHandler;
use App\Chatbot\Domain\Service\FunctionRegistryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ChatbotController extends AbstractController
{
    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function __invoke(
        Request $request,
        SendMessageHandler $sendMessageHandler,
        FunctionRegistryService $functionRegistry
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $firstName = $data['firstName'] ?? '';
        $message = $data['message'] ?? '';

        if (empty($firstName)) {
            return new JsonResponse(['error' => 'Name required'], 400);
        }

        try {
            // Get functions from the registry service
            $callableFunctions = $functionRegistry->getCallableFunctions();
            $functionMetadata = $functionRegistry->getFunctionMetadata();

            // Create DTO
            $dto = new ChatMessageDTO($firstName, $message);

            // Handle message through application layer
            $result = $sendMessageHandler->handle($dto, $callableFunctions, $functionMetadata);

            return new JsonResponse([
                'success' => true,
                'messages' => $result['messages'],
                'response' => $result['response'],
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to communicate with agent: ' . $e->getMessage(),
            ], 500);
        }
    }
}
