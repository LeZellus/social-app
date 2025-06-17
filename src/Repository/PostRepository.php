<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * OPTIMISÉ : Trouve les posts récents avec leurs publications (évite N+1)
     */
    public function findRecentByUserWithPublications(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.postPublications', 'pp')
            ->leftJoin('pp.socialAccount', 'sa')
            ->addSelect('pp', 'sa') // CRUCIAL : charge les relations en une seule requête
            ->where('p.user = :user')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * OPTIMISÉ : Posts pour l'index avec toutes les données nécessaires
     */
    public function findForIndex(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.postPublications', 'pp')
            ->leftJoin('pp.socialAccount', 'sa')
            ->addSelect('pp', 'sa') // Charge tout en une fois
            ->where('p.user = :user')
            ->orderBy('p.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * NOUVEAU : Stats rapides sans charger tous les posts
     */
    public function getQuickStats(User $user): array
    {
        $qb = $this->createQueryBuilder('p');
        
        $result = $qb
            ->select('p.status, COUNT(p.id) as count')
            ->where('p.user = :user')
            ->groupBy('p.status')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'draft' => 0,
            'scheduled' => 0,
            'published' => 0,
            'failed' => 0
        ];

        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Trouve les posts récents d'un utilisateur
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les posts par statut
     */
    public function findByUserAndStatus(User $user, string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.status = :status')
            ->orderBy('p.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les posts programmés à publier
     */
    public function findScheduledPosts(\DateTimeInterface $before = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.scheduledAt IS NOT NULL')
            ->setParameter('status', 'scheduled');

        if ($before) {
            $qb->andWhere('p.scheduledAt <= :before')
               ->setParameter('before', $before);
        }

        return $qb->orderBy('p.scheduledAt', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte les posts par statut pour un utilisateur
     */
    public function countByStatus(User $user): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->where('p.user = :user')
            ->groupBy('p.status')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $stats = [
            'draft' => 0,
            'scheduled' => 0,
            'published' => 0,
            'failed' => 0
        ];

        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Recherche dans les posts
     */
    public function search(User $user, string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.title LIKE :query OR p.content LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * DÉPRÉCIÉ : Utiliser findForIndex() à la place
     */
    public function findWithPublications(User $user): array
    {
        return $this->findForIndex($user);
    }

    public function save(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}