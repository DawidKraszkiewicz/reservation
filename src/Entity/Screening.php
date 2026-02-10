<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ScreeningRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScreeningRepository::class)]
class Screening
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'screenings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Room $room = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $movieTitle = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startsAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $endsAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $price = null;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'screening')]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;
        return $this;
    }

    public function getMovieTitle(): ?string
    {
        return $this->movieTitle;
    }

    public function setMovieTitle(string $movieTitle): static
    {
        $this->movieTitle = $movieTitle;
        return $this;
    }

    public function getStartsAt(): ?\DateTimeInterface
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeInterface $startsAt): static
    {
        $this->startsAt = $startsAt;
        return $this;
    }

    public function getEndsAt(): ?\DateTimeInterface
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeInterface $endsAt): static
    {
        $this->endsAt = $endsAt;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    /**
     * @return array<int>
     */
    public function getReservedSeatIds(): array
    {
        $seatIds = [];
        foreach ($this->reservations as $reservation) {
            if ($reservation->getStatus() !== 'cancelled') {
                $seatIds = array_merge($seatIds, $reservation->getSeats());
            }
        }
        return array_unique($seatIds);
    }

    /**
     * @return array<int>
     */
    public function getAvailableSeatIds(): array
    {
        $room = $this->getRoom();
        if (!$room) {
            return [];
        }

        $allSeatIds = $room->getSeats()->map(fn(Seat $seat) => $seat->getId())->toArray();
        $reservedSeatIds = $this->getReservedSeatIds();

        return array_values(array_diff($allSeatIds, $reservedSeatIds));
    }
}
