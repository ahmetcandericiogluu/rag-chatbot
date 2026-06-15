<?php

namespace App\Repository;

use App\Entity\RagSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class RagSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RagSource::class);
    }

    public function findOneByTenantAndDocument(?string $tenantId, string $documentId): ?RagSource
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'documentId' => $documentId,
        ]);
    }

    public function save(RagSource $source, bool $flush = true): void
    {
        $this->getEntityManager()->persist($source);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}