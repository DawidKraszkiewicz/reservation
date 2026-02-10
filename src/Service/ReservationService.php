<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Screening;
use App\Exception\SeatsNotAvailableException;
use App\Repository\ReservationRepository;
use App\Repository\SeatRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
        private readonly SeatRepository $seatRepository,
    ) {
    }

    /**
     * @param array<int> $seatIds
     * @throws SeatsNotAvailableException
     */
    public function createReservation(
        Screening $screening,
        array $seatIds,
        string $customerName,
        string $customerEmail
    ): Reservation {
        $this->entityManager->beginTransaction();

        try {
            // Validate seats belong to the screening's room
            $this->validateSeatsBelongToRoom($screening, $seatIds);

            // Check seat availability with lock
            $this->validateSeatsAvailable($screening, $seatIds);

            // Calculate total price
            $totalPrice = $this->calculateTotalPrice($screening, count($seatIds));

            // Create reservation
            $reservation = new Reservation();
            $reservation->setScreening($screening);
            $reservation->setCustomerName($customerName);
            $reservation->setCustomerEmail($customerEmail);
            $reservation->setSeats($seatIds);
            $reservation->setTotalPrice($totalPrice);
            $reservation->confirm();

            $this->reservationRepository->save($reservation, true);
            $this->entityManager->commit();

            return $reservation;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @param array<int> $seatIds
     * @throws SeatsNotAvailableException
     */
    public function validateSeatsAvailable(Screening $screening, array $seatIds): void
    {
        $reservedSeatIds = $this->reservationRepository->getReservedSeatIds($screening);
        $unavailableSeats = array_intersect($seatIds, $reservedSeatIds);

        if (!empty($unavailableSeats)) {
            throw new SeatsNotAvailableException(
                sprintf('Seats are already reserved: %s', implode(', ', $unavailableSeats))
            );
        }
    }

    /**
     * @param array<int> $seatIds
     * @throws SeatsNotAvailableException
     */
    public function validateSeatsBelongToRoom(Screening $screening, array $seatIds): void
    {
        $room = $screening->getRoom();
        if (!$room) {
            throw new SeatsNotAvailableException('Screening has no room assigned');
        }

        $roomSeatIds = $room->getSeats()->map(fn($seat) => $seat->getId())->toArray();
        $invalidSeats = array_diff($seatIds, $roomSeatIds);

        if (!empty($invalidSeats)) {
            throw new SeatsNotAvailableException(
                sprintf('Invalid seat IDs for this room: %s', implode(', ', $invalidSeats))
            );
        }
    }

    public function calculateTotalPrice(Screening $screening, int $seatCount): string
    {
        $pricePerSeat = (float) $screening->getPrice();
        $total = $pricePerSeat * $seatCount;

        return number_format($total, 2, '.', '');
    }

    /**
     * @param array<int> $seatIds
     */
    public function areSeatIdsValid(array $seatIds): bool
    {
        if (empty($seatIds)) {
            return false;
        }

        foreach ($seatIds as $seatId) {
            if (!is_int($seatId) || $seatId <= 0) {
                return false;
            }
        }

        return true;
    }
}
