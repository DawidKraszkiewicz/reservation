<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminRoomControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->setupDatabase();
        $this->adminToken = $this->createAdminAndGetToken();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    public function testListRoomsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/rooms');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testListRoomsWithValidToken(): void
    {
        $this->createRoom('Test Room', 5, 10);

        $this->client->request(
            'GET',
            '/api/admin/rooms',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertCount(1, $data);
        $this->assertEquals('Test Room', $data[0]['name']);
    }

    public function testCreateRoom(): void
    {
        $this->client->request(
            'POST',
            '/api/admin/rooms',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => 'New Room',
                'rows' => 8,
                'seatsPerRow' => 15,
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertEquals('New Room', $data['name']);
        $this->assertEquals(8, $data['rows']);
        $this->assertEquals(15, $data['seatsPerRow']);
        $this->assertEquals(120, $data['totalSeats']);
    }

    public function testCreateRoomValidationError(): void
    {
        $this->client->request(
            'POST',
            '/api/admin/rooms',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => '',
                'rows' => -1,
                'seatsPerRow' => 0,
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testUpdateRoom(): void
    {
        $room = $this->createRoom('Old Name', 5, 10);

        $this->client->request(
            'PUT',
            '/api/admin/rooms/' . $room->getId(),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => 'New Name',
                'isActive' => false,
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('New Name', $data['name']);
        $this->assertFalse($data['isActive']);
    }

    public function testDeleteRoom(): void
    {
        $room = $this->createRoom('To Delete', 5, 10);
        $roomId = $room->getId();

        $this->client->request(
            'DELETE',
            '/api/admin/rooms/' . $roomId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        // Verify room is deleted
        $this->entityManager->clear();
        $deletedRoom = $this->entityManager->getRepository(Room::class)->find($roomId);
        $this->assertNull($deletedRoom);
    }

    public function testDeleteRoomNotFound(): void
    {
        $this->client->request(
            'DELETE',
            '/api/admin/rooms/99999',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    private function setupDatabase(): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Exception $e) {
            // Ignore
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
                // Ignore
            }
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createAdminAndGetToken(): string
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'password'));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwtManager->create($admin);
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
}
