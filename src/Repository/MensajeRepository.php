<?php
// src/Repository/MensajeRepository.php
namespace App\Repository;

use App\Entity\Mensaje;
use App\Entity\Sala;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mensaje>
 */
class MensajeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mensaje::class);
    }

    /**
     * Obtiene los mensajes de una sala ordenados por fecha
     */
    public function findBySala(Sala $sala, int $limit = 50)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.sala = :sala')
            ->setParameter('sala', $sala)
            ->orderBy('m.fechaCreacion', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ CORREGIDO: Busca la conversación privada entre dos usuarios
     * El error era que no manejaba bien los NULLs
     */
    public function findConversacionPrivada(User $me, User $other, int $limit = 50)
    {
        return $this->createQueryBuilder('m')
            ->where('(m.autor = :me AND m.receptor = :other) OR (m.autor = :other AND m.receptor = :me)')
            ->andWhere('m.sala IS NULL')  // ← IMPORTANTE: Solo mensajes privados
            ->setParameter('me', $me)
            ->setParameter('other', $other)
            ->orderBy('m.fechaCreacion', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ CORREGIDO: Contar salas distintas donde el usuario escribió hoy
     */
    public function countDistinctSalasByUserAndDateRange(
        User $user,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): int {
        try {
            $qb = $this->createQueryBuilder('m')
                ->select('COUNT(DISTINCT IDENTITY(m.sala))')
                ->where('m.autor = :user')
                ->andWhere('m.fechaCreacion >= :start')
                ->andWhere('m.fechaCreacion < :end')
                ->andWhere('m.sala IS NOT NULL')  // ← IMPORTANTE: Excluir privados
                ->setParameter('user', $user)
                ->setParameter('start', $start)
                ->setParameter('end', $end);

            $result = $qb->getQuery()->getSingleScalarResult();
            return (int) $result;
        } catch (\Exception $e) {
            // Loggear error pero no romper la app
            error_log('Error en countDistinctSalasByUserAndDateRange: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ NUEVO: Contar mensajes de un usuario hoy (para estadísticas)
     */
    public function countByUserAndDateRange(
        User $user,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): int {
        try {
            return (int) $this->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.autor = :user')
                ->andWhere('m.fechaCreacion >= :start')
                ->andWhere('m.fechaCreacion < :end')
                ->setParameter('user', $user)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            error_log('Error en countByUserAndDateRange: ' . $e->getMessage());
            return 0;
        }
    }
}
