<?php
// src/Repository/UserSessionRepository.php
namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    /**
     * Encontrar sesiones activas de un usuario
     */
    public function findActiveSessions(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('s.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtener tiempo total de estudio hoy (en segundos)
     */
    public function getTodayStudyTime(User $user): int
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.durationSeconds)')
            ->where('s.user = :user')
            ->andWhere('s.status = :status')
            ->andWhere('s.startTime >= :start')
            ->andWhere('s.startTime < :end')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result ?: 0;
    }

    /**
     * Obtener sesiones de un rango de fechas
     */
    public function findByDateRange(User $user, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.startTime >= :start')
            ->andWhere('s.startTime <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
