<?php

namespace App\Repository;

use App\Entity\Issue;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Issue>
 */
class IssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Issue::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{issues: list<Issue>, total: int}
     */
    public function findByFilters(array $filters, int $page = 1, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $page = max(1, $page);

        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.assignee', 'assignee')->addSelect('assignee')
            ->leftJoin('i.reporter', 'reporter')->addSelect('reporter')
            ->leftJoin('i.project', 'project')->addSelect('project')
            ->leftJoin('i.task', 'task')->addSelect('task');

        if (!empty($filters['status'])) {
            $statuses = (array) $filters['status'];
            $qb->andWhere($qb->expr()->in('i.status', ':status'))
                ->setParameter('status', $statuses);
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere($qb->expr()->in('i.priority', ':priority'))
                ->setParameter('priority', (array) $filters['priority']);
        }

        if (!empty($filters['severity'])) {
            $qb->andWhere($qb->expr()->in('i.severity', ':severity'))
                ->setParameter('severity', (array) $filters['severity']);
        }

        if (!empty($filters['assignee'])) {
            $qb->andWhere('assignee.id = :assignee')
                ->setParameter('assignee', $filters['assignee']);
        }

        if (!empty($filters['reporter'])) {
            $qb->andWhere('reporter.id = :reporter')
                ->setParameter('reporter', $filters['reporter']);
        }

        if (!empty($filters['project'])) {
            $qb->andWhere('project.id = :project')
                ->setParameter('project', $filters['project']);
        }

        if (!empty($filters['task'])) {
            $qb->andWhere('task.id = :task')
                ->setParameter('task', $filters['task']);
        }

        if (!empty($filters['sprint'])) {
            $qb->leftJoin('i.sprint', 'sprint')->addSelect('sprint');
            $qb->andWhere('sprint.id = :sprint')
                ->setParameter('sprint', $filters['sprint']);
        }

        if (!empty($filters['search'])) {
            $pattern = '%' . mb_strtolower((string) $filters['search']) . '%';
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('LOWER(i.title)', ':search'),
                $qb->expr()->like('LOWER(i.description)', ':search'),
                $qb->expr()->like('LOWER(i.issueNumber)', ':search'),
            ))->setParameter('search', $pattern);
        }

        if (!empty($filters['dateFrom'])) {
            $dateFrom = $this->normaliseDate($filters['dateFrom']);
            if ($dateFrom !== null) {
                $qb->andWhere($qb->expr()->orX('i.dueDate >= :dateFrom', 'i.taskDueDate >= :dateFrom'))
                    ->setParameter('dateFrom', $dateFrom);
            }
        }

        if (!empty($filters['dateTo'])) {
            $dateTo = $this->normaliseDate($filters['dateTo']);
            if ($dateTo !== null) {
                $qb->andWhere($qb->expr()->orX('i.dueDate <= :dateTo', 'i.taskDueDate <= :dateTo'))
                    ->setParameter('dateTo', $dateTo);
            }
        }

        $qb->orderBy('i.createdAt', 'DESC');

        $paginator = new Paginator($qb);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $issues = array_values(iterator_to_array($paginator->getIterator()));
        $total = count($paginator);

        return [
            'issues' => $issues,
            'total' => $total,
        ];
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findIssueWithDetails(int $id): ?Issue
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.project', 'project')->addSelect('project')
            ->leftJoin('i.task', 'task')->addSelect('task')
            ->leftJoin('i.assignee', 'assignee')->addSelect('assignee')
            ->leftJoin('i.reporter', 'reporter')->addSelect('reporter')
            ->leftJoin('i.sprint', 'sprint')->addSelect('sprint')
            ->leftJoin('i.labelEntities', 'labels')->addSelect('labels')
            ->leftJoin('i.comments', 'comments')->addSelect('comments')
            ->leftJoin('comments.user', 'commentUser')->addSelect('commentUser')
            ->leftJoin('comments.editedBy', 'commentEditor')->addSelect('commentEditor')
            ->leftJoin('i.attachments', 'attachments')->addSelect('attachments')
            ->leftJoin('attachments.user', 'attachmentUser')->addSelect('attachmentUser')
            ->leftJoin('i.activities', 'activities')->addSelect('activities')
            ->leftJoin('activities.user', 'activityUser')->addSelect('activityUser')
            ->leftJoin('i.watchers', 'watchers')->addSelect('watchers')
            ->leftJoin('watchers.user', 'watcherUser')->addSelect('watcherUser')
            ->leftJoin('i.sourceLinks', 'sourceLinks')->addSelect('sourceLinks')
            ->leftJoin('sourceLinks.targetIssue', 'linkedTarget')->addSelect('linkedTarget')
            ->leftJoin('sourceLinks.createdBy', 'linkCreator')->addSelect('linkCreator')
            ->leftJoin('i.targetLinks', 'targetLinks')->addSelect('targetLinks')
            ->leftJoin('targetLinks.sourceIssue', 'linkedSource')->addSelect('linkedSource')
            ->andWhere('i.id = :id')
            ->setParameter('id', $id)
            ->addOrderBy('comments.createdAt', 'DESC')
            ->addOrderBy('activities.createdAt', 'DESC');

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return list<Issue>
     */
    public function findOpenIssuesByAssignee(User $user): array
    {
        $statuses = ['new', 'open', 'in_progress', 'in_review', 'blocked'];

        return $this->createQueryBuilder('i')
            ->where('i.assignee = :assignee')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('assignee', $user)
            ->setParameter('statuses', $statuses)
            ->orderBy('i.priority', 'DESC')
            ->addOrderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Issue>
     */
    public function findIssuesByDueDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('i')
            ->where('(i.dueDate BETWEEN :start AND :end) OR (i.taskDueDate BETWEEN :start AND :end)')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('COALESCE(i.dueDate, i.taskDueDate)', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function getIssueStatistics(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();

        $statusCounts = $this->groupCount($connection, $table, 'status');
        $priorityCounts = $this->groupCount($connection, $table, 'priority');
        $typeCounts = $this->groupCount($connection, $table, 'issue_type');

        $avgResolutionSeconds = $connection->fetchOne(
            sprintf('SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) FROM %s WHERE resolved_at IS NOT NULL', $table)
        );
        $avgResolutionSeconds = $avgResolutionSeconds !== false ? (float) $avgResolutionSeconds : null;

        $openCount = (int) $connection->fetchOne(
            sprintf("SELECT COUNT(*) FROM %s WHERE status IN ('new', 'open', 'in_progress', 'in_review', 'blocked')", $table)
        );
        $closedCount = (int) $connection->fetchOne(
            sprintf("SELECT COUNT(*) FROM %s WHERE status IN ('resolved', 'closed')", $table)
        );

        return [
            'status' => $statusCounts,
            'priority' => $priorityCounts,
            'type' => $typeCounts,
            'averageResolutionSeconds' => $avgResolutionSeconds,
            'open' => $openCount,
            'closed' => $closedCount,
        ];
    }

    /**
     * @return list<Issue>
     */
    public function searchIssues(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $lower = mb_strtolower($query);
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.assignee', 'assignee')->addSelect('assignee')
            ->leftJoin('i.project', 'project')->addSelect('project');

        $qb->andWhere($qb->expr()->orX(
            'LOWER(i.title) LIKE :search',
            'LOWER(i.description) LIKE :search',
            'LOWER(i.issueNumber) LIKE :searchExact'
        ))
            ->setParameter('search', '%' . $lower . '%')
            ->setParameter('searchExact', $lower . '%')
            ->setMaxResults(50)
            ->orderBy('i.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupCount(Connection $connection, string $table, string $column): array
    {
        $sql = sprintf('SELECT %s AS label, COUNT(*) AS total FROM %s GROUP BY %s ORDER BY total DESC', $column, $table, $column);

        return $connection->fetchAllAssociative($sql);
    }

    private function normaliseDate(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
