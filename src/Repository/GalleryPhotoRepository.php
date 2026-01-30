<?php

namespace App\Repository;

use App\Entity\Gallery;
use App\Entity\GalleryPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GalleryPhoto>
 */
class GalleryPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryPhoto::class);
    }

    /**
     * @return list<GalleryPhoto>
     */
    public function findAllOrdered(Gallery $gallery): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.gallery = :gallery')
            ->setParameter('gallery', $gallery)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.uploadedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextDisplayOrder(Gallery $gallery): int
    {
        $max = $this->createQueryBuilder('p')
            ->select('MAX(p.displayOrder) as maxOrder')
            ->andWhere('p.gallery = :gallery')
            ->setParameter('gallery', $gallery)
            ->getQuery()
            ->getSingleScalarResult();

        return $max ? ((int) $max + 1) : 0;
    }

    public function save(GalleryPhoto $photo, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($photo);
        if ($flush) {
            $em->flush();
        }
    }

    public function remove(GalleryPhoto $photo, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->remove($photo);
        if ($flush) {
            $em->flush();
        }
    }
}
