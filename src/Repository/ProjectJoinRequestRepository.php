<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectJoinRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectJoinRequest>
 */
class ProjectJoinRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectJoinRequest::class);
    }

    /**
     * @return ProjectJoinRequest[]
     */
    public function findPendingByProject(Project $project): array
    {
        return $this->findBy(
            [
                'project' => $project,
                'status' => 'pending',
            ],
            [
                'createdAt' => 'DESC',
            ]
        );
    }

    public function findPendingRequest(Project $project, User $user): ?ProjectJoinRequest
    {
        return $this->findOneBy([
            'project' => $project,
            'user' => $user,
            'status' => 'pending',
        ]);
    }
}