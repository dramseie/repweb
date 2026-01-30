<?php

namespace App\Repository;

use App\Entity\Gallery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gallery>
 */
class GalleryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gallery::class);
    }

    public function save(Gallery $gallery): void
    {
        $this->getEntityManager()->persist($gallery);
    }

    public function findOneBySlug(string $slug): ?Gallery
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findOneByToken(string $token): ?Gallery
    {
        return $this->findOneBy(['shareToken' => $token]);
    }
}
