<?php

namespace App\Service;

use App\Entity\RagSource;
use App\Repository\RagSourceRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class PostgresVectorStore
{
    public function __construct(
        private Connection $connection,
        private RagSourceRepository $ragSourceRepository,
    ) {
    }

    public function upsertSource(
        string $documentId,
        string $sourcePath,
        string $sourceType,
        string $title,
        string $contentType,
        string $checksum,
        int $tokenCount = 0,
        ?string $tenantId = null,
        ?string $sourceUrl = null,
        string $language = 'tr',
        array $metadata = [],
    ): RagSource {
        $source = $this->ragSourceRepository->findOneByTenantAndDocument($tenantId, $documentId);

        if (!$source) {
            $source = new RagSource();
            $source->setTenantId($tenantId);
            $source->setDocumentId($documentId);
        }

        $source
            ->setSourcePath($sourcePath)
            ->setSourceUrl($sourceUrl)
            ->setSourceType($sourceType)
            ->setTitle($title)
            ->setLanguage($language)
            ->setContentType($contentType)
            ->setChecksum($checksum)
            ->setTokenCount($tokenCount)
            ->setMetadata($metadata)
            ->setIsActive(true)
            ->touch();

        $this->ragSourceRepository->save($source);

        return $source;
    }

    public function clearChunksForSource(RagSource $source): void
    {
        $this->connection->executeStatement(
            'DELETE FROM rag_chunks WHERE source_id = :source_id',
            [
                'source_id' => $source->getId(),
            ]
        );
    }

    public function insertChunk(
        RagSource $source,
        string $content,
        array $embedding,
        int $chunkNo,
        ?string $heading = null,
        string $language = 'tr',
        string $contentType = 'text/plain',
        array $metadata = [],
        ?int $startOffset = null,
        ?int $endOffset = null,
    ): void {
        $this->connection->executeStatement(
            '
            INSERT INTO rag_chunks (
                source_id,
                tenant_id,
                document_id,
                embedding_model,
                embedding_dims,
                chunk_no,
                heading,
                content,
                content_hash,
                token_count,
                char_count,
                start_offset,
                end_offset,
                language,
                content_type,
                is_active,
                metadata,
                embedding
            ) VALUES (
                :source_id,
                :tenant_id,
                :document_id,
                :embedding_model,
                :embedding_dims,
                :chunk_no,
                :heading,
                :content,
                :content_hash,
                :token_count,
                :char_count,
                :start_offset,
                :end_offset,
                :language,
                :content_type,
                true,
                :metadata::jsonb,
                :embedding
            )
            ',
            [
                'source_id' => $source->getId(),
                'tenant_id' => $source->getTenantId(),
                'document_id' => $source->getDocumentId(),
                'embedding_model' => 'embeddinggemma',
                'embedding_dims' => count($embedding),
                'chunk_no' => $chunkNo,
                'heading' => $heading,
                'content' => $content,
                'content_hash' => hash('sha256', $content),
                'token_count' => $this->estimateTokenCount($content),
                'char_count' => mb_strlen($content, 'UTF-8'),
                'start_offset' => $startOffset,
                'end_offset' => $endOffset,
                'language' => $language,
                'content_type' => $contentType,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'embedding' => $this->vectorToSql($embedding),
            ]
        );
    }

    public function search(
        array $queryVector,
        int $limit = 5,
        ?string $tenantId = null,
        string $language = 'tr',
        string $embeddingModel = 'embeddinggemma',
    ): array {
        return $this->connection->fetchAllAssociative(
            '
            SELECT
                c.id AS chunk_id,
                c.source_id,
                c.document_id,
                c.chunk_no,
                c.heading,
                c.content,
                c.language,
                c.content_type,
                c.metadata,
                s.title AS source_title,
                s.source_path,
                s.source_url,
                s.source_type,
                1 - (c.embedding <=> :query_embedding) AS score
            FROM rag_chunks c
            INNER JOIN rag_sources s ON s.id = c.source_id
            WHERE c.is_active = true
              AND s.is_active = true
              AND c.language = :language
              AND c.embedding_model = :embedding_model
              AND (
                    c.tenant_id IS NULL
                    OR c.tenant_id = :tenant_id
              )
            ORDER BY c.embedding <=> :query_embedding
            LIMIT :limit
            ',
            [
                'query_embedding' => $this->vectorToSql($queryVector),
                'tenant_id' => $tenantId,
                'language' => $language,
                'embedding_model' => $embeddingModel,
                'limit' => $limit,
            ],
            [
                'limit' => ParameterType::INTEGER,            
            ]
        );
    }

    public function markSourceIndexed(RagSource $source): void
    {
        $source->markIndexed();

        $this->ragSourceRepository->save($source);
    }

    private function vectorToSql(array $vector): string
    {
        return '[' . implode(',', array_map(
            static fn (float|int $value): string => (string) $value,
            $vector
        )) . ']';
    }

    private function estimateTokenCount(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return max(1, count($words));
    }
}