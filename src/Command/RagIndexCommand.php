<?php

namespace App\Command;

use App\Service\OllamaEmbeddingService;
use App\Service\PostgresVectorStore;
use App\Service\RagTextChunker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:rag:index',
    description: 'Knowledge klasöründeki dosyaları PostgreSQL + pgvector içine indexler.'
)]
final class RagIndexCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly OllamaEmbeddingService $embeddingService,
        private readonly PostgresVectorStore $vectorStore,
        private readonly RagTextChunker $chunker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $knowledgeDir = $this->kernel->getProjectDir() . '/data/knowledge';

        if (!is_dir($knowledgeDir)) {
            $output->writeln('<error>Knowledge klasörü bulunamadı: data/knowledge</error>');

            return Command::FAILURE;
        }

        $files = $this->findTextFiles($knowledgeDir);

        if ($files === []) {
            $output->writeln('<error>Indexlenecek .txt dosyası bulunamadı.</error>');

            return Command::FAILURE;
        }

        foreach ($files as $filePath) {
            $this->indexFile($filePath, $knowledgeDir, $output);
        }

        $output->writeln('<info>Tüm knowledge dosyaları PostgreSQL + pgvector içine indexlendi.</info>');

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function findTextFiles(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if (strtolower($file->getExtension()) !== 'txt') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    private function indexFile(string $filePath, string $knowledgeDir, OutputInterface $output): void
    {
        $content = file_get_contents($filePath);

        if ($content === false || trim($content) === '') {
            $output->writeln(sprintf('<comment>Boş veya okunamayan dosya atlandı: %s</comment>', $filePath));

            return;
        }

        $relativePath = 'data/knowledge/' . str_replace('\\', '/', ltrim(str_replace($knowledgeDir, '', $filePath), '\\/'));
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);

        $documentId = 'help:' . $this->slugify($fileName);
        $title = $this->titleFromFileName($fileName);
        $checksum = hash('sha256', $content);

        $chunks = $this->chunker->chunk($content);

        if ($chunks === []) {
            $output->writeln(sprintf('<comment>Chunk üretilemedi, dosya atlandı: %s</comment>', $relativePath));

            return;
        }

        $source = $this->vectorStore->upsertSource(
            documentId: $documentId,
            sourcePath: $relativePath,
            sourceType: 'help',
            title: $title,
            contentType: 'text/plain',
            checksum: $checksum,
            tokenCount: $this->estimateTokenCount($content),
            tenantId: null,
            sourceUrl: null,
            language: 'tr',
            metadata: [
                'project' => 'idefix-rag-chatbot',
                'file_name' => basename($filePath),
            ],
        );

        $this->vectorStore->clearChunksForSource($source);

        $output->writeln(sprintf('<info>Dosya indexleniyor:</info> %s', $relativePath));
        $output->writeln(sprintf('Chunk sayısı: %d', count($chunks)));

        foreach ($chunks as $chunk) {
            $textForEmbedding = trim(($chunk->heading ? $chunk->heading . "\n" : '') . $chunk->content);

            $embedding = $this->embeddingService->embed($textForEmbedding);

            $this->vectorStore->insertChunk(
                source: $source,
                content: $chunk->content,
                embedding: $embedding,
                chunkNo: $chunk->chunkNo,
                heading: $chunk->heading,
                language: 'tr',
                contentType: 'text/plain',
                metadata: [
                    'file_name' => basename($filePath),
                    'chunk_no' => $chunk->chunkNo,
                ],
                startOffset: $chunk->startOffset,
                endOffset: $chunk->endOffset,
            );

            $output->writeln(sprintf('  - Chunk #%d indexlendi', $chunk->chunkNo));
        }

        $this->vectorStore->markSourceIndexed($source);
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');

        $map = [
            'ı' => 'i',
            'ğ' => 'g',
            'ü' => 'u',
            'ş' => 's',
            'ö' => 'o',
            'ç' => 'c',
        ];

        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : 'document';
    }

    private function titleFromFileName(string $fileName): string
    {
        $title = str_replace(['-', '_'], ' ', $fileName);

        return mb_convert_case($title, MB_CASE_TITLE, 'UTF-8');
    }

    private function estimateTokenCount(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return max(1, count($words));
    }
}