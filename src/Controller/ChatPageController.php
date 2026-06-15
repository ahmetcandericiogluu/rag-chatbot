<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChatPageController extends AbstractController
{
    #[Route('/chat-ui', name: 'chat_ui', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('chat/index.html.twig');
    }
}