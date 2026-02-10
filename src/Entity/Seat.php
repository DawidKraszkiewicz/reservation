<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeatRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_seat_in_room', columns: ['room_id', 'row_label', 'seat_number'])]
class Seat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'seats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Room $room = null;

    #[ORM\Column(length: 2)]
    private ?string $rowLabel = null;

    #[ORM\Column]
    private ?int $seatNumber = null;

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

    public function getRowLabel(): ?string
    {
        return $this->rowLabel;
    }

    public function setRowLabel(string $rowLabel): static
    {
        $this->rowLabel = $rowLabel;
        return $this;
    }

    public function getSeatNumber(): ?int
    {
        return $this->seatNumber;
    }

    public function setSeatNumber(int $seatNumber): static
    {
        $this->seatNumber = $seatNumber;
        return $this;
    }

    public function getLabel(): string
    {
        return sprintf('%s%d', $this->rowLabel, $this->seatNumber);
    }
}
