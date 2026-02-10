<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Room;
use App\Repository\RoomRepository;
use App\Repository\ScreeningRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PublicController extends AbstractController
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly ScreeningRepository $screeningRepository,
    ) {
    }

    #[Route('/rooms', name: 'api_rooms_list', methods: ['GET'])]
    public function getRooms(): JsonResponse
    {
        $rooms = $this->roomRepository->findAllActive();

        $data = array_map(function (Room $room) {
            return [
                'id' => $room->getId(),
                'name' => $room->getName(),
                'rows' => $room->getRows(),
                'seatsPerRow' => $room->getSeatsPerRow(),
                'totalSeats' => $room->getTotalSeats(),
            ];
        }, $rooms);

        return $this->json($data);
    }

    #[Route('/screenings', name: 'api_screenings_list', methods: ['GET'])]
    public function getScreenings(): JsonResponse
    {
        $screenings = $this->screeningRepository->findUpcoming();

        $data = array_map(function ($screening) {
            return [
                'id' => $screening->getId(),
                'movieTitle' => $screening->getMovieTitle(),
                'room' => [
                    'id' => $screening->getRoom()->getId(),
                    'name' => $screening->getRoom()->getName(),
                ],
                'startsAt' => $screening->getStartsAt()->format('Y-m-d H:i:s'),
                'endsAt' => $screening->getEndsAt()->format('Y-m-d H:i:s'),
                'price' => $screening->getPrice(),
                'availableSeats' => count($screening->getAvailableSeatIds()),
            ];
        }, $screenings);

        return $this->json($data);
    }

    #[Route('/screenings/{id}', name: 'api_screening_show', methods: ['GET'])]
    public function getScreening(int $id): JsonResponse
    {
        $screening = $this->screeningRepository->find($id);

        if (!$screening) {
            return $this->json(['error' => 'Screening not found'], Response::HTTP_NOT_FOUND);
        }

        $room = $screening->getRoom();
        $seats = [];
        $reservedSeatIds = $screening->getReservedSeatIds();

        foreach ($room->getSeats() as $seat) {
            $seats[] = [
                'id' => $seat->getId(),
                'row' => $seat->getRowLabel(),
                'number' => $seat->getSeatNumber(),
                'label' => $seat->getLabel(),
                'isAvailable' => !in_array($seat->getId(), $reservedSeatIds),
            ];
        }

        return $this->json([
            'id' => $screening->getId(),
            'movieTitle' => $screening->getMovieTitle(),
            'room' => [
                'id' => $room->getId(),
                'name' => $room->getName(),
                'rows' => $room->getRows(),
                'seatsPerRow' => $room->getSeatsPerRow(),
            ],
            'startsAt' => $screening->getStartsAt()->format('Y-m-d H:i:s'),
            'endsAt' => $screening->getEndsAt()->format('Y-m-d H:i:s'),
            'price' => $screening->getPrice(),
            'seats' => $seats,
        ]);
    }
}
