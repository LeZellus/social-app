<?php

namespace App\Repository;

use App\Entity\PostPublication;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostPublication>
 */
class PostPublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostPublication::class);
    }

    public function findUniqueDestinationsByUserAndPlatform(User $user, string $platform): array
    {
        return $this->createQueryBuilder('pp')
            ->select('DISTINCT pp.destination')
            ->join('pp.socialAccount', 'sa')
            ->where('sa.user = :user')
            ->andWhere('sa.platform = :platform')
            ->andWhere('pp.destination IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('platform', $platform)
            ->orderBy('pp.destination', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}