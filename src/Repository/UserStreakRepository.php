<?php
// src/Repository/UserStreakRepository.php
namespace App\Repository;

use App\Entity\User;
use App\Entity\UserStreak;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserStreak>
 */
class UserStreakRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserStreak::class);
    }

    /**
     * Encontrar o crear streak de un usuario
     */
    public function findOrCreateByUser(User $user): UserStreak
    {
        $streak = $this->findOneBy(['user' => $user]);

        if (!$streak) {
            $streak = new UserStreak();
            $streak->setUser($user);
            $streak->setCurrentStreak(1);
            $streak->setLongestStreak(1);
            $streak->updateStreakForToday();

            // Nota: Aquí NO hacemos flush, lo hace el servicio
        }

        return $streak;
    }

    /**
     * Obtener usuarios con rachas activas (para rankings)
     */
    public function findActiveStreaks(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.currentStreak > 0')
            ->orderBy('s.currentStreak', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtener récord histórico de un usuario
     */
    public function getUserRecord(User $user): int
    {
        $streak = $this->findOneBy(['user' => $user]);
        return $streak ? $streak->getLongestStreak() : 0;
    }
}
