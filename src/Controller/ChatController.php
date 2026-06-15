<?php

namespace App\Controller;

use App\Service\RagChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChatController extends AbstractController
{
    public function __construct(
        private readonly RagChatService $ragChatService,
    ) {
    }

    #[Route('/chat', name: 'app_chat')]
    public function index(Request $request): Response
    {
        $question = $request->query->get('q', 'İade süresi kaç gündür?');

        try {
            $result = $this->ragChatService->ask($question);
        } catch (\Throwable $exception) {
            return new Response($exception->getMessage(), 500);
        }

        return new Response($result['answer']);
    }
}