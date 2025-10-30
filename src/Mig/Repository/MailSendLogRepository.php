<?php
namespace App\Mig\Repository;

use App\Mig\Entity\MailSendLog as Entity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MailSendLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity::class);
    }

    public function save(object $entity, bool $flush = true): void
    {
        $em = $this->getEntityManager(); $em->persist($entity);
        if ($flush) { $em->flush(); }
    }

    public function remove(object $entity, bool $flush = true): void
    {
        $em = $this->getEntityManager(); $em->remove($entity);
        if ($flush) { $em->flush(); }
    }
}
