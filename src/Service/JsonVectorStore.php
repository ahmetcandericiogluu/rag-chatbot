<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

final readonly class JsonVectorStore
{
    public function __construct(
        private KernelInterface $kernel,
    ) {
    }

    public function save(array $documents): void
    {
        $path = $this->getStorePath();

        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $path,
            json_encode($documents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function all(): array
    {
        $path = $this->getStorePath();

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        $documents = json_decode($content, true);

        if (!is_array($documents)) {
            return [];
        }

        return $documents;
    }

    public function search(array $queryVector, int $limit = 3): array
    {
        $documents = $this->all();

        foreach ($documents as &$document) {
            $document['score'] = $this->cosineSimilarity(
                $queryVector,
                $document['embedding']
            );
        }

        unset($document);

        usort($documents, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($documents, 0, $limit);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    private function getStorePath(): string
    {
        return $this->kernel->getProjectDir() . '/var/rag/vector-store.json';
    }
}