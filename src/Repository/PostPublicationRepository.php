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

    /**
     * Trouve les publications en attente
     */
    public function findPendingPublications(): array
    {
        return $this->createQueryBuilder('pp')
            ->join('pp.post', 'p')
            ->join('pp.socialAccount', 'sa')
            ->where('pp.status = :status')
            ->andWhere('sa.isActive = true')
            ->setParameter('status', 'pending')
            ->orderBy('pp.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les publications par statut et utilisateur
     */
    public function findByUserAndStatus(User $user, string $status): array
    {
        return $this->createQueryBuilder('pp')
            ->join('pp.post', 'p')
            ->where('p.user = :user')
            ->andWhere('pp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->orderBy('pp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les publications programmées à publier
     */
    public function findScheduledPublications(\DateTimeInterface $before = null): array
    {
        $qb = $this->createQueryBuilder('pp')
            ->join('pp.post', 'p')
            ->where('pp.status = :status')
            ->andWhere('p.scheduledAt IS NOT NULL')
            ->setParameter('status', 'pending');

        if ($before) {
            $qb->andWhere('p.scheduledAt <= :before')
               ->setParameter('before', $before);
        }

        return $qb->orderBy('p.scheduledAt', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Trouve les publications échouées avec retry possible
     */
    public function findFailedWithRetryPossible(int $maxRetries = 3): array
    {
        return $this->createQueryBuilder('pp')
            ->where('pp.status = :status')
            ->andWhere('pp.retryCount < :maxRetries')
            ->setParameter('status', 'failed')
            ->setParameter('maxRetries', $maxRetries)
            ->orderBy('pp.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par destination
     */
    public function getDestinationStats(string $destination, int $socialAccountId): array
    {
        $result = $this->createQueryBuilder('pp')
            ->select('pp.status, COUNT(pp.id) as count')
            ->where('pp.destination = :destination')
            ->andWhere('pp.socialAccount = :accountId')
            ->groupBy('pp.status')
            ->setParameter('destination', $destination)
            ->setParameter('accountId', $socialAccountId)
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'published' => 0,
            'pending' => 0,
            'failed' => 0,
            'scheduled' => 0
        ];

        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Statistiques par utilisateur
     */
    public function getUserStats(User $user): array
    {
        $result = $this->createQueryBuilder('pp')
            ->select('pp.status, COUNT(pp.id) as count')
            ->join('pp.post', 'p')
            ->where('p.user = :user')
            ->groupBy('pp.status')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'published' => 0,
            'pending' => 0,
            'failed' => 0,
            'scheduled' => 0
        ];

        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Trouve les publications récentes d'un utilisateur
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('pp')
            ->join('pp.post', 'p')
            ->join('pp.socialAccount', 'sa')
            ->where('p.user = :user')
            ->orderBy('pp.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function save(PostPublication $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PostPublication $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}