<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private ?string $name = null;

    #[ORM\Column(name: '`rows`')]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $rows = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $seatsPerRow = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, Seat> */
    #[ORM\OneToMany(targetEntity: Seat::class, mappedBy: 'room', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $seats;

    /** @var Collection<int, Screening> */
    #[ORM\OneToMany(targetEntity: Screening::class, mappedBy: 'room')]
    private Collection $screenings;

    public function __construct()
    {
        $this->seats = new ArrayCollection();
        $this->screenings = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getRows(): ?int
    {
        return $this->rows;
    }

    public function setRows(int $rows): static
    {
        $this->rows = $rows;
        return $this;
    }

    public function getSeatsPerRow(): ?int
    {
        return $this->seatsPerRow;
    }

    public function setSeatsPerRow(int $seatsPerRow): static
    {
        $this->seatsPerRow = $seatsPerRow;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, Seat>
     */
    public function getSeats(): Collection
    {
        return $this->seats;
    }

    public function addSeat(Seat $seat): static
    {
        if (!$this->seats->contains($seat)) {
            $this->seats->add($seat);
            $seat->setRoom($this);
        }
        return $this;
    }

    public function removeSeat(Seat $seat): static
    {
        if ($this->seats->removeElement($seat)) {
            if ($seat->getRoom() === $this) {
                $seat->setRoom(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Screening>
     */
    public function getScreenings(): Collection
    {
        return $this->screenings;
    }

    public function getTotalSeats(): int
    {
        return ($this->rows ?? 0) * ($this->seatsPerRow ?? 0);
    }

    public function generateSeats(): void
    {
        $this->seats->clear();

        for ($row = 0; $row < $this->rows; $row++) {
            $rowLabel = chr(65 + $row); // A, B, C, ...
            for ($seatNum = 1; $seatNum <= $this->seatsPerRow; $seatNum++) {
                $seat = new Seat();
                $seat->setRowLabel($rowLabel);
                $seat->setSeatNumber($seatNum);
                $this->addSeat($seat);
            }
        }
    }
}
