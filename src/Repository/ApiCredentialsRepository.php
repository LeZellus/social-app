<?php

namespace App\Repository;

use App\Entity\ApiCredentials;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiCredentials>
 */
class ApiCredentialsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiCredentials::class);
    }

    /**
     * Trouve les clefs actives d'un utilisateur pour une plateforme
     */
    public function findActiveByUserAndPlatform(User $user, string $platform): ?ApiCredentials
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.user = :user')
            ->andWhere('ac.platform = :platform')
            ->andWhere('ac.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('platform', $platform)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les clefs actives d'un utilisateur
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.user = :user')
            ->andWhere('ac.isActive = true')
            ->orderBy('ac.platform', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a des clefs configurées pour une plateforme
     */
    public function hasCredentialsForPlatform(User $user, string $platform): bool
    {
        $count = $this->createQueryBuilder('ac')
            ->select('COUNT(ac.id)')
            ->where('ac.user = :user')
            ->andWhere('ac.platform = :platform')
            ->andWhere('ac.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('platform', $platform)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Compte les clefs par statut pour un utilisateur
     */
    public function countByStatus(User $user): array
    {
        $result = $this->createQueryBuilder('ac')
            ->select('ac.isActive, COUNT(ac.id) as count')
            ->where('ac.user = :user')
            ->groupBy('ac.isActive')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $stats = ['active' => 0, 'inactive' => 0];
        
        foreach ($result as $row) {
            $key = $row['isActive'] ? 'active' : 'inactive';
            $stats[$key] = (int) $row['count'];
        }

        return $stats;
    }
}