<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Room;
use App\Repository\RoomRepository;
use App\Service\RoomService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin')]
class RoomController extends AbstractController
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly RoomService $roomService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/rooms', name: 'api_admin_rooms_list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $rooms = $this->roomRepository->findAll();

        $data = array_map(fn(Room $room) => $this->serializeRoom($room), $rooms);

        return $this->json($data);
    }

    #[Route('/rooms/{id}', name: 'api_admin_rooms_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['error' => 'Room not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeRoom($room));
    }

    #[Route('/rooms', name: 'api_admin_rooms_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $constraints = new Assert\Collection([
            'name' => [new Assert\NotBlank(), new Assert\Length(['min' => 1, 'max' => 100])],
            'rows' => [new Assert\NotBlank(), new Assert\Type('integer'), new Assert\Positive()],
            'seatsPerRow' => [new Assert\NotBlank(), new Assert\Type('integer'), new Assert\Positive()],
        ]);

        $violations = $this->validator->validate($data, $constraints);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->createRoom(
            $data['name'],
            $data['rows'],
            $data['seatsPerRow']
        );

        return $this->json($this->serializeRoom($room), Response::HTTP_CREATED);
    }

    #[Route('/rooms/{id}', name: 'api_admin_rooms_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['error' => 'Room not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $constraints = new Assert\Collection([
            'fields' => [
                'name' => new Assert\Optional([new Assert\Length(['min' => 1, 'max' => 100])]),
                'rows' => new Assert\Optional([new Assert\Type('integer'), new Assert\Positive()]),
                'seatsPerRow' => new Assert\Optional([new Assert\Type('integer'), new Assert\Positive()]),
                'isActive' => new Assert\Optional([new Assert\Type('boolean')]),
            ],
            'allowExtraFields' => false,
        ]);

        $violations = $this->validator->validate($data, $constraints);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->updateRoom(
            $room,
            $data['name'] ?? null,
            $data['rows'] ?? null,
            $data['seatsPerRow'] ?? null
        );

        if (isset($data['isActive'])) {
            $data['isActive'] ? $this->roomService->activateRoom($room) : $this->roomService->deactivateRoom($room);
        }

        return $this->json($this->serializeRoom($room));
    }

    #[Route('/rooms/{id}', name: 'api_admin_rooms_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['error' => 'Room not found'], Response::HTTP_NOT_FOUND);
        }

        $this->roomService->deleteRoom($room);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRoom(Room $room): array
    {
        return [
            'id' => $room->getId(),
            'name' => $room->getName(),
            'rows' => $room->getRows(),
            'seatsPerRow' => $room->getSeatsPerRow(),
            'totalSeats' => $room->getTotalSeats(),
            'isActive' => $room->isActive(),
            'createdAt' => $room->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $room->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
