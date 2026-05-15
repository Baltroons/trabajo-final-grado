<?php
// src/Entity/UserStreak.php
namespace App\Entity;

use App\Repository\UserStreakRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserStreakRepository::class)]
class UserStreak
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'streak')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $currentStreak = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $longestStreak = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $lastActivityDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $streakStartDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastActivityDate = new \DateTimeImmutable();
    }

    // Getters & Setters...
    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self {
        $this->user = $user;
        return $this;
    }

    public function getCurrentStreak(): int { return $this->currentStreak; }
    public function setCurrentStreak(int $currentStreak): self {
        $this->currentStreak = $currentStreak;
        return $this;
    }

    public function getLongestStreak(): int { return $this->longestStreak; }
    public function setLongestStreak(int $longestStreak): self {
        $this->longestStreak = $longestStreak;
        return $this;
    }

    public function getLastActivityDate(): ?\DateTimeImmutable { return $this->lastActivityDate; }
    public function setLastActivityDate(\DateTimeImmutable $lastActivityDate): self {
        $this->lastActivityDate = $lastActivityDate;
        return $this;
    }

    public function getStreakStartDate(): ?\DateTimeImmutable { return $this->streakStartDate; }
    public function setStreakStartDate(?\DateTimeImmutable $streakStartDate): self {
        $this->streakStartDate = $streakStartDate;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Calcula si la racha sigue viva o debe reiniciarse
     */
    public function updateStreakForToday(): void
    {
        $today = new \DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');

        if ($this->lastActivityDate == $today) {
            // Ya se registró actividad hoy, no hacer nada
            return;
        } elseif ($this->lastActivityDate == $yesterday) {
            // Actividad ayer, continuar racha
            $this->currentStreak++;
            $this->lastActivityDate = $today;

            if ($this->currentStreak > $this->longestStreak) {
                $this->longestStreak = $this->currentStreak;
            }
        } else {
            // Se rompió la racha (pasaron más de 1 día)
            $this->currentStreak = 1;
            $this->lastActivityDate = $today;
            $this->streakStartDate = $today;
        }

        $this->updatedAt = new \DateTimeImmutable();
    }
}
