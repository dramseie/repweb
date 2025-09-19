<?php
namespace App\Repository;

use App\Entity\UiWidgetTab;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UiWidgetTabRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    { parent::__construct($registry, UiWidgetTab::class); }

    public function countForUser(int $userId): int
    {
        return (int)$this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.ownerUserId = :u')->setParameter('u', $userId)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return UiWidgetTab[] */
	// src/Repository/UiWidgetTabRepository.php
	public function forUser(int $userId): array
	{
		return $this->createQueryBuilder('t')
			->andWhere('t.ownerUserId = :u')->setParameter('u', $userId)
			->orderBy('t.sortOrder', 'ASC')
			->addOrderBy('t.id', 'ASC')
			->getQuery()->getResult();
	}


	public function findByOwnerOrdered(int $ownerUserId): array
	{
		return $this->createQueryBuilder('t')
			->andWhere('t.ownerUserId = :uid')
			->setParameter('uid', $ownerUserId)
			->orderBy('t.sortOrder', 'ASC')
			->getQuery()->getResult();
	}

	public function findVisibleForUserOrdered(?int $ownerUserId): array
	{
		return $this->createQueryBuilder('t')
			->andWhere('t.isHidden = :hidden')->setParameter('hidden', false)
			->andWhere('(t.ownerUserId = :uid) OR (t.ownerUserId IS NULL AND t.isSystem = true)')
			->setParameter('uid', $ownerUserId)
			->orderBy('t.sortOrder', 'ASC')
			->addOrderBy('t.id', 'ASC')
			->getQuery()
			->getResult();
	}	
}
