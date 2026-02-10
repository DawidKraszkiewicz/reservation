<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Screening;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Screening>
 */
class ScreeningRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Screening::class);
    }

    public function save(Screening $screening, bool $flush = false): void
    {
        $this->getEntityManager()->persist($screening);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<Screening>
     */
    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.room', 'r')
            ->andWhere('s.startsAt > :now')
            ->andWhere('r.isActive = :active')
            ->setParameter('now', new \DateTime())
            ->setParameter('active', true)
            ->orderBy('s.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Screening>
     */
    public function findByRoomAndDate(int $roomId, \DateTimeInterface $date): array
    {
        $startOfDay = \DateTime::createFromInterface($date)->setTime(0, 0, 0);
        $endOfDay = \DateTime::createFromInterface($date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('s')
            ->andWhere('s.room = :roomId')
            ->andWhere('s.startsAt BETWEEN :start AND :end')
            ->setParameter('roomId', $roomId)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('s.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
