<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Exception\SeatsNotAvailableException;
use App\Repository\ScreeningRepository;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly ScreeningRepository $screeningRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/reservations', name: 'api_reservations_create', methods: ['POST'])]
    public function createReservation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Validate input
        $constraints = new Assert\Collection([
            'screeningId' => [new Assert\NotBlank(), new Assert\Type('integer')],
            'seats' => [
                new Assert\NotBlank(),
                new Assert\Type('array'),
                new Assert\Count(['min' => 1]),
                new Assert\All([new Assert\Type('integer'), new Assert\Positive()]),
            ],
            'customerName' => [new Assert\NotBlank(), new Assert\Length(['min' => 2, 'max' => 255])],
            'customerEmail' => [new Assert\NotBlank(), new Assert\Email()],
        ]);

        $violations = $this->validator->validate($data, $constraints);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        // Find screening
        $screening = $this->screeningRepository->find($data['screeningId']);

        if (!$screening) {
            return $this->json(['error' => 'Screening not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if screening is in the future
        if ($screening->getStartsAt() < new \DateTime()) {
            return $this->json(['error' => 'Cannot book for past screenings'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $reservation = $this->reservationService->createReservation(
                $screening,
                $data['seats'],
                $data['customerName'],
                $data['customerEmail']
            );

            return $this->json([
                'id' => $reservation->getId(),
                'screening' => [
                    'id' => $screening->getId(),
                    'movieTitle' => $screening->getMovieTitle(),
                    'startsAt' => $screening->getStartsAt()->format('Y-m-d H:i:s'),
                ],
                'customerName' => $reservation->getCustomerName(),
                'customerEmail' => $reservation->getCustomerEmail(),
                'seats' => $reservation->getSeats(),
                'seatCount' => $reservation->getSeatCount(),
                'totalPrice' => $reservation->getTotalPrice(),
                'status' => $reservation->getStatus(),
                'createdAt' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
            ], Response::HTTP_CREATED);
        } catch (SeatsNotAvailableException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }
}
