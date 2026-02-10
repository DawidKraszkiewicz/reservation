<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    public function save(Room $room, bool $flush = false): void
    {
        $this->getEntityManager()->persist($room);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Room $room, bool $flush = false): void
    {
        $this->getEntityManager()->remove($room);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<Room>
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
