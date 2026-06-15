<?php

namespace App\Entity;

use App\Repository\RagSourceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RagSourceRepository::class)]
#[ORM\Table(name: 'rag_sources')]
class RagSource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(type: 'guid', unique: true)]
    private string $publicId;

    #[ORM\Column(type: 'guid', nullable: true)]
    private ?string $tenantId = null;

    #[ORM\Column(length: 190)]
    private string $documentId;

    #[ORM\Column(type: 'text')]
    private string $sourcePath;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 32)]
    private string $sourceType;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 10)]
    private string $language = 'tr';

    #[ORM\Column(length: 100)]
    private string $contentType;

    #[ORM\Column(length: 64)]
    private string $checksum;

    #[ORM\Column(type: 'integer')]
    private int $tokenCount = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastIndexedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->publicId = uuid_create(UUID_TYPE_RANDOM);
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function setDocumentId(string $documentId): self
    {
        $this->documentId = $documentId;

        return $this;
    }

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(string $sourcePath): self
    {
        $this->sourcePath = $sourcePath;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): self
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function setChecksum(string $checksum): self
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function getTokenCount(): int
    {
        return $this->tokenCount;
    }

    public function setTokenCount(int $tokenCount): self
    {
        $this->tokenCount = $tokenCount;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getLastIndexedAt(): ?\DateTimeImmutable
    {
        return $this->lastIndexedAt;
    }

    public function markIndexed(): self
    {
        $now = new \DateTimeImmutable();

        $this->lastIndexedAt = $now;
        $this->updatedAt = $now;

        return $this;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}