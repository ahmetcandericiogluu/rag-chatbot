<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OllamaEmbeddingService
{
    public function __construct(
        #[Autowire(service: 'ollama.client')]
        private HttpClientInterface $httpClient,
    ) {
    }

    public function embed(string $text): array
    {
        $response = $this->httpClient->request('POST', '/api/embed', [
            'json' => [
                'model' => 'embeddinggemma',
                'input' => $text,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['embeddings'][0]) || !is_array($data['embeddings'][0])) {
            throw new \RuntimeException('Ollama embedding response beklenen formatta değil.');
        }

        return $data['embeddings'][0];
    }
}