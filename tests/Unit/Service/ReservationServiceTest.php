<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Reservation;
use App\Entity\Room;
use App\Entity\Screening;
use App\Entity\Seat;
use App\Exception\SeatsNotAvailableException;
use App\Repository\ReservationRepository;
use App\Repository\SeatRepository;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ReservationServiceTest extends TestCase
{
    private ReservationService $service;
    private EntityManagerInterface $entityManager;
    private ReservationRepository $reservationRepository;
    private SeatRepository $seatRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->reservationRepository = $this->createMock(ReservationRepository::class);
        $this->seatRepository = $this->createMock(SeatRepository::class);

        $this->service = new ReservationService(
            $this->entityManager,
            $this->reservationRepository,
            $this->seatRepository
        );
    }

    public function testCalculateTotalPriceForSingleSeat(): void
    {
        $screening = $this->createScreeningWithPrice('25.00');

        $result = $this->service->calculateTotalPrice($screening, 1);

        $this->assertEquals('25.00', $result);
    }

    public function testCalculateTotalPriceForMultipleSeats(): void
    {
        $screening = $this->createScreeningWithPrice('25.00');

        $result = $this->service->calculateTotalPrice($screening, 3);

        $this->assertEquals('75.00', $result);
    }

    public function testCalculateTotalPriceWithDecimalPrice(): void
    {
        $screening = $this->createScreeningWithPrice('19.99');

        $result = $this->service->calculateTotalPrice($screening, 2);

        $this->assertEquals('39.98', $result);
    }

    public function testValidateSeatsBelongToRoomThrowsExceptionForInvalidSeats(): void
    {
        $room = $this->createRoomWithSeats([1, 2, 3, 4, 5]);
        $screening = $this->createScreeningWithRoom($room);

        $this->expectException(SeatsNotAvailableException::class);
        $this->expectExceptionMessage('Invalid seat IDs for this room: 99, 100');

        $this->service->validateSeatsBelongToRoom($screening, [1, 2, 99, 100]);
    }

    public function testValidateSeatsBelongToRoomPassesForValidSeats(): void
    {
        $room = $this->createRoomWithSeats([1, 2, 3, 4, 5]);
        $screening = $this->createScreeningWithRoom($room);

        $this->service->validateSeatsBelongToRoom($screening, [1, 2, 3]);
        $this->assertTrue(true);
    }

    public function testValidateSeatsAvailableThrowsExceptionForReservedSeats(): void
    {
        $screening = $this->createMock(Screening::class);

        $this->reservationRepository
            ->expects($this->once())
            ->method('getReservedSeatIds')
            ->with($screening)
            ->willReturn([1, 2, 3]);

        $this->expectException(SeatsNotAvailableException::class);
        $this->expectExceptionMessage('Seats are already reserved: 2, 3');

        $this->service->validateSeatsAvailable($screening, [2, 3, 4, 5]);
    }

    public function testValidateSeatsAvailablePassesForAvailableSeats(): void
    {
        $screening = $this->createMock(Screening::class);

        $this->reservationRepository
            ->expects($this->once())
            ->method('getReservedSeatIds')
            ->with($screening)
            ->willReturn([1, 2, 3]);

        $this->service->validateSeatsAvailable($screening, [4, 5, 6]);
        $this->assertTrue(true);
    }

    public function testAreSeatIdsValidReturnsFalseForEmptyArray(): void
    {
        $this->assertFalse($this->service->areSeatIdsValid([]));
    }

    public function testAreSeatIdsValidReturnsFalseForNonPositiveIds(): void
    {
        $this->assertFalse($this->service->areSeatIdsValid([1, 2, 0]));
        $this->assertFalse($this->service->areSeatIdsValid([1, -1, 3]));
    }

    public function testAreSeatIdsValidReturnsTrueForValidIds(): void
    {
        $this->assertTrue($this->service->areSeatIdsValid([1, 2, 3, 4, 5]));
    }

    public function testCreateReservationSuccess(): void
    {
        $room = $this->createRoomWithSeats([1, 2, 3, 4, 5]);
        $screening = $this->createScreeningWithRoom($room);
        $screening->method('getPrice')->willReturn('25.00');

        $this->reservationRepository
            ->method('getReservedSeatIds')
            ->willReturn([]);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('commit');
        $this->reservationRepository->expects($this->once())->method('save');

        $reservation = $this->service->createReservation(
            $screening,
            [1, 2],
            'Jan Kowalski',
            'jan@example.com'
        );

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals('Jan Kowalski', $reservation->getCustomerName());
        $this->assertEquals('jan@example.com', $reservation->getCustomerEmail());
        $this->assertEquals([1, 2], $reservation->getSeats());
        $this->assertEquals('50.00', $reservation->getTotalPrice());
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $reservation->getStatus());
    }

    public function testCreateReservationRollsBackOnError(): void
    {
        $room = $this->createRoomWithSeats([1, 2, 3, 4, 5]);
        $screening = $this->createScreeningWithRoom($room);

        $this->reservationRepository
            ->method('getReservedSeatIds')
            ->willReturn([1, 2]);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('commit');

        $this->expectException(SeatsNotAvailableException::class);

        $this->service->createReservation(
            $screening,
            [1, 2],
            'Jan Kowalski',
            'jan@example.com'
        );
    }

    private function createScreeningWithPrice(string $price): Screening
    {
        $screening = $this->createMock(Screening::class);
        $screening->method('getPrice')->willReturn($price);
        return $screening;
    }

    private function createScreeningWithRoom(Room $room): Screening
    {
        $screening = $this->createMock(Screening::class);
        $screening->method('getRoom')->willReturn($room);
        return $screening;
    }

    /**
     * @param array<int> $seatIds
     */
    private function createRoomWithSeats(array $seatIds): Room
    {
        $room = $this->createMock(Room::class);
        $seats = new \Doctrine\Common\Collections\ArrayCollection();

        foreach ($seatIds as $id) {
            $seat = $this->createMock(Seat::class);
            $seat->method('getId')->willReturn($id);
            $seats->add($seat);
        }

        $room->method('getSeats')->willReturn($seats);

        return $room;
    }
}
