<?php

namespace App\Repository;

use App\Entity\SocialAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialAccount>
 */
class SocialAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialAccount::class);
    }

    /**
     * Trouve les comptes actifs d'un utilisateur
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('sa')
            ->where('sa.user = :user')
            ->andWhere('sa.isActive = true')
            ->orderBy('sa.platform', 'ASC')
            ->addOrderBy('sa.accountName', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un compte par plateforme pour un utilisateur
     */
    public function findByUserAndPlatform(User $user, string $platform): ?SocialAccount
    {
        return $this->createQueryBuilder('sa')
            ->where('sa.user = :user')
            ->andWhere('sa.platform = :platform')
            ->andWhere('sa.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('platform', $platform)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les comptes avec des tokens valides
     */
    public function findWithValidTokens(User $user): array
    {
        return $this->createQueryBuilder('sa')
            ->where('sa.user = :user')
            ->andWhere('sa.isActive = true')
            ->andWhere('
                sa.tokenExpiresAt IS NULL 
                OR sa.tokenExpiresAt > :now
            ')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les comptes par plateforme
     */
    public function countByPlatform(User $user): array
    {
        $result = $this->createQueryBuilder('sa')
            ->select('sa.platform, COUNT(sa.id) as count')
            ->where('sa.user = :user')
            ->andWhere('sa.isActive = true')
            ->groupBy('sa.platform')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['platform']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Trouve les comptes avec des tokens expirés
     */
    public function findExpiredTokens(): array
    {
        return $this->createQueryBuilder('sa')
            ->where('sa.tokenExpiresAt IS NOT NULL')
            ->andWhere('sa.tokenExpiresAt <= :now')
            ->andWhere('sa.isActive = true')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a un compte pour une plateforme
     */
    public function hasAccountForPlatform(User $user, string $platform): bool
    {
        $count = $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->where('sa.user = :user')
            ->andWhere('sa.platform = :platform')
            ->andWhere('sa.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('platform', $platform)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Trouve les comptes avec leurs statistiques de publication
     */
    public function findWithPublicationStats(User $user): array
    {
        return $this->createQueryBuilder('sa')
            ->select('sa', 'COUNT(pp.id) as publicationCount')
            ->leftJoin('sa.postPublications', 'pp')
            ->where('sa.user = :user')
            ->groupBy('sa.id')
            ->orderBy('sa.platform', 'ASC')
            ->addOrderBy('sa.accountName', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Désactive les comptes expirés
     */
    public function deactivateExpiredAccounts(): int
    {
        return $this->createQueryBuilder('sa')
            ->update()
            ->set('sa.isActive', 'false')
            ->where('sa.tokenExpiresAt IS NOT NULL')
            ->andWhere('sa.tokenExpiresAt <= :now')
            ->andWhere('sa.isActive = true')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function save(SocialAccount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SocialAccount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUserAndPlatformIgnoreStatus(User $user, string $platform): ?SocialAccount
    {
        return $this->createQueryBuilder('sa')
            ->where('sa.user = :user')
            ->andWhere('sa.platform = :platform')
            ->setParameter('user', $user)
            ->setParameter('platform', $platform)
            ->getQuery()
            ->getOneOrNullResult();
    }
}