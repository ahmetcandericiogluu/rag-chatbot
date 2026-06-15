<?php

namespace App\Controller\Api;

use App\Service\RagChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ChatApiController extends AbstractController
{
    public function __construct(
        private readonly RagChatService $ragChatService,
    ) {
    }

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'error' => 'Geçersiz JSON body.',
            ], 400);
        }

        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            return $this->json([
                'error' => 'message alanı zorunludur.',
            ], 400);
        }

        try {
            $answer = $this->ragChatService->ask($message);
        } catch (\Throwable $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], 500);
        }

        return $this->json([
            'message' => $message,
            'answer' => $answer['answer'],
            'sources' => $answer['sources'],
        ]);
    }
}