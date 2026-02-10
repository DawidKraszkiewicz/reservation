<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Room;
use App\Entity\Screening;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@cinema.pl');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Create rooms
        $room1 = new Room();
        $room1->setName('Sala 1 - Standard');
        $room1->setRows(10);
        $room1->setSeatsPerRow(12);
        $room1->generateSeats();
        $manager->persist($room1);

        $room2 = new Room();
        $room2->setName('Sala 2 - VIP');
        $room2->setRows(5);
        $room2->setSeatsPerRow(8);
        $room2->generateSeats();
        $manager->persist($room2);

        $room3 = new Room();
        $room3->setName('Sala 3 - IMAX');
        $room3->setRows(15);
        $room3->setSeatsPerRow(20);
        $room3->generateSeats();
        $manager->persist($room3);

        // Create screenings
        $movies = [
            ['title' => 'Avatar 3', 'duration' => 180, 'price' => '35.00'],
            ['title' => 'Dune: Część Druga', 'duration' => 166, 'price' => '30.00'],
            ['title' => 'Oppenheimer', 'duration' => 180, 'price' => '28.00'],
            ['title' => 'Barbie', 'duration' => 114, 'price' => '25.00'],
        ];

        $rooms = [$room1, $room2, $room3];
        $startDate = new \DateTime('tomorrow 14:00');

        foreach ($rooms as $room) {
            $screeningTime = clone $startDate;

            foreach ($movies as $movie) {
                $screening = new Screening();
                $screening->setRoom($room);
                $screening->setMovieTitle($movie['title']);
                $screening->setStartsAt(clone $screeningTime);

                $endTime = clone $screeningTime;
                $endTime->modify('+' . $movie['duration'] . ' minutes');
                $screening->setEndsAt($endTime);

                $screening->setPrice($movie['price']);
                $manager->persist($screening);

                // Next screening starts 30 minutes after previous ends
                $screeningTime = clone $endTime;
                $screeningTime->modify('+30 minutes');
            }
        }

        $manager->flush();
    }
}
