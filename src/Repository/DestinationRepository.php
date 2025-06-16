<?php

namespace App\Repository;

use App\Entity\Destination;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Destination>
 */
class DestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destination::class);
    }

    /**
     * Trouve les destinations actives d'un utilisateur
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.socialAccount', 'sa')
            ->where('d.user = :user')
            ->andWhere('d.isActive = true')
            ->andWhere('sa.isActive = true')
            ->orderBy('sa.platform', 'ASC')
            ->addOrderBy('d.displayName', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les destinations par plateforme
     */
    public function findByPlatform(User $user, string $platform): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.socialAccount', 'sa')
            ->where('d.user = :user')
            ->andWhere('sa.platform = :platform')
            ->andWhere('d.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('platform', $platform)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les destinations par statut
     */
    public function countByStatus(User $user): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('d.isActive, COUNT(d.id) as count')
            ->where('d.user = :user')
            ->groupBy('d.isActive')
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

    /**
     * Trouve les destinations avec leurs statistiques de publication
     */
    public function findWithPublicationStats(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->select('d', 'sa', 'COUNT(pp.id) as publicationCount')
            ->join('d.socialAccount', 'sa')
            ->leftJoin('App\Entity\PostPublication', 'pp', 'WITH', 'pp.destination = d.name AND pp.socialAccount = sa')
            ->where('d.user = :user')
            ->groupBy('d.id', 'sa.id')
            ->orderBy('sa.platform', 'ASC')
            ->addOrderBy('d.displayName', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par nom ou plateforme
     */
    public function search(User $user, string $query): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.socialAccount', 'sa')
            ->where('d.user = :user')
            ->andWhere('
                d.name LIKE :query 
                OR d.displayName LIKE :query 
                OR sa.platform LIKE :query
                OR sa.accountName LIKE :query
            ')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('d.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Destination $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Destination $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}