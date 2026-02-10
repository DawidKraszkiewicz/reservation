<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Room;
use App\Entity\Screening;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ReservationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->setupDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    public function testCreateReservationSuccess(): void
    {
        $room = $this->createRoom('Test Room', 5, 10);
        $screening = $this->createScreening($room, 'Test Movie', '25.00');

        $seatIds = $room->getSeats()->map(fn($s) => $s->getId())->slice(0, 2);

        $this->client->request(
            'POST',
            '/api/reservations',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'screeningId' => $screening->getId(),
                'seats' => $seatIds,
                'customerName' => 'Jan Kowalski',
                'customerEmail' => 'jan@example.com',
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertEquals('Jan Kowalski', $data['customerName']);
        $this->assertEquals('jan@example.com', $data['customerEmail']);
        $this->assertEquals('50.00', $data['totalPrice']);
        $this->assertEquals('confirmed', $data['status']);
        $this->assertCount(2, $data['seats']);
    }

    public function testCreateReservationValidationError(): void
    {
        $this->client->request(
            'POST',
            '/api/reservations',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'screeningId' => 1,
                'seats' => [],
                'customerName' => '',
                'customerEmail' => 'invalid-email',
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testCreateReservationScreeningNotFound(): void
    {
        $this->client->request(
            'POST',
            '/api/reservations',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'screeningId' => 99999,
                'seats' => [1, 2],
                'customerName' => 'Jan Kowalski',
                'customerEmail' => 'jan@example.com',
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertEquals('Screening not found', $data['error']);
    }

    public function testCreateReservationSeatConflict(): void
    {
        $room = $this->createRoom('Test Room', 5, 10);
        $screening = $this->createScreening($room, 'Test Movie', '25.00');

        $seatIds = $room->getSeats()->map(fn($s) => $s->getId())->slice(0, 2);

        $this->client->request(
            'POST',
            '/api/reservations',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'screeningId' => $screening->getId(),
                'seats' => $seatIds,
                'customerName' => 'Jan Kowalski',
                'customerEmail' => 'jan@example.com',
            ])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/api/reservations',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'screeningId' => $screening->getId(),
                'seats' => $seatIds,
                'customerName' => 'Anna Nowak',
                'customerEmail' => 'anna@example.com',
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertStringContainsString('already reserved', $data['error']);
    }

    public function testGetScreeningsReturnsUpcoming(): void
    {
        $room = $this->createRoom('Test Room', 5, 10);
        $this->createScreening($room, 'Future Movie', '25.00');

        $this->client->request('GET', '/api/screenings');

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertIsArray($data);
    }

    public function testGetScreeningDetails(): void
    {
        $room = $this->createRoom('Test Room', 5, 10);
        $screening = $this->createScreening($room, 'Test Movie', '25.00');

        $this->client->request('GET', '/api/screenings/' . $screening->getId());

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('Test Movie', $data['movieTitle']);
        $this->assertArrayHasKey('seats', $data);
        $this->assertCount(50, $data['seats']);
    }

    public function testGetRooms(): void
    {
        $this->createRoom('Room 1', 5, 10);
        $this->createRoom('Room 2', 8, 12);

        $this->client->request('GET', '/api/rooms');

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertCount(2, $data);
    }

    private function setupDatabase(): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Exception $e) {
        }

        $schemaTool->createSchema($metadata);
    }

    private function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['reservation', 'screening', 'seat', 'room', 'user'] as $table) {
            try {
                $connection->executeStatement("TRUNCATE TABLE `{$table}`");
            } catch (\Exception $e) {
            }
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createRoom(string $name, int $rows, int $seatsPerRow): Room
    {
        $room = new Room();
        $room->setName($name);
        $room->setRows($rows);
        $room->setSeatsPerRow($seatsPerRow);
        $room->generateSeats();

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        return $room;
    }

    private function createScreening(Room $room, string $movieTitle, string $price): Screening
    {
        $screening = new Screening();
        $screening->setRoom($room);
        $screening->setMovieTitle($movieTitle);
        $screening->setStartsAt(new \DateTime('+1 day'));
        $screening->setEndsAt(new \DateTime('+1 day +2 hours'));
        $screening->setPrice($price);

        $this->entityManager->persist($screening);
        $this->entityManager->flush();

        return $screening;
    }
}
