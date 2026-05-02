<?php

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
     * Busca la conversación privada entre dos usuarios (tú y el receptor)
     */
    public function findConversacionPrivada(User $me, User $other, int $limit = 50)
    {
        return $this->createQueryBuilder('m')
            ->where('(m.autor = :me AND m.receptor = :other) OR (m.autor = :other AND m.receptor = :me)')
            ->setParameter('me', $me)
            ->setParameter('other', $other)
            ->orderBy('m.fechaCreacion', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
