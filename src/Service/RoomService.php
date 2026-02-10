<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Room;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;

class RoomService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoomRepository $roomRepository,
    ) {
    }

    public function createRoom(string $name, int $rows, int $seatsPerRow): Room
    {
        $room = new Room();
        $room->setName($name);
        $room->setRows($rows);
        $room->setSeatsPerRow($seatsPerRow);
        $room->generateSeats();

        $this->roomRepository->save($room, true);

        return $room;
    }

    public function updateRoom(Room $room, ?string $name = null, ?int $rows = null, ?int $seatsPerRow = null): Room
    {
        $regenerateSeats = false;

        if ($name !== null) {
            $room->setName($name);
        }

        if ($rows !== null && $rows !== $room->getRows()) {
            $room->setRows($rows);
            $regenerateSeats = true;
        }

        if ($seatsPerRow !== null && $seatsPerRow !== $room->getSeatsPerRow()) {
            $room->setSeatsPerRow($seatsPerRow);
            $regenerateSeats = true;
        }

        if ($regenerateSeats) {
            $room->generateSeats();
        }

        $this->entityManager->flush();

        return $room;
    }

    public function deleteRoom(Room $room): void
    {
        $this->roomRepository->remove($room, true);
    }

    public function activateRoom(Room $room): Room
    {
        $room->setIsActive(true);
        $this->entityManager->flush();

        return $room;
    }

    public function deactivateRoom(Room $room): Room
    {
        $room->setIsActive(false);
        $this->entityManager->flush();

        return $room;
    }
}
