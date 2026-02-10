<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Screening;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function save(Reservation $reservation, bool $flush = false): void
    {
        $this->getEntityManager()->persist($reservation);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<Reservation>
     */
    public function findActiveByScreening(Screening $screening): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.screening = :screening')
            ->andWhere('r.status != :cancelled')
            ->setParameter('screening', $screening)
            ->setParameter('cancelled', Reservation::STATUS_CANCELLED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int>
     */
    public function getReservedSeatIds(Screening $screening): array
    {
        $reservations = $this->findActiveByScreening($screening);
        $seatIds = [];

        foreach ($reservations as $reservation) {
            $seatIds = array_merge($seatIds, $reservation->getSeats());
        }

        return array_unique($seatIds);
    }
}
