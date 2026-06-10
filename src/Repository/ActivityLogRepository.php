<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Project;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

//    /**
//     * @return ActivityLog[] Returns an array of ActivityLog objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ActivityLog
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
public function findByProjectAndType(
    Project $project,
    ?string $type = null
): array {
    $qb = $this->createQueryBuilder('a')
        ->andWhere('a.project = :project')
        ->setParameter('project', $project)
        ->orderBy('a.createdAt', 'DESC');

    if ($type) {
        $qb->andWhere('a.type LIKE :type')
           ->setParameter('type', '%' . $type . '%');
    }

    return $qb->getQuery()->getResult();
}
}
