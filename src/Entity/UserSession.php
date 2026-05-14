<?php
// src/Entity/UserSession.php
namespace App\Entity;

use App\Repository\UserSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sala $sala = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $durationSeconds = 0;

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $activityType = null;

    #[ORM\PrePersist]
    public function setStartTimeValue(): void {
        if ($this->startTime === null) {
            $this->startTime = new \DateTimeImmutable();
        }
    }

    // Getters & Setters...
    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getSala(): ?Sala { return $this->sala; }
    public function setSala(?Sala $sala): self { $this->sala = $sala; return $this; }

    public function getStartTime(): ?\DateTimeImmutable { return $this->startTime; }
    public function setStartTime(?\DateTimeImmutable $startTime): self {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable { return $this->endTime; }
    public function setEndTime(?\DateTimeImmutable $endTime): self {
        $this->endTime = $endTime;
        if ($this->startTime && $endTime) {
            $this->durationSeconds = $endTime->getTimestamp() - $this->startTime->getTimestamp();
        }
        return $this;
    }

    public function getDurationSeconds(): int { return $this->durationSeconds; }
    public function setDurationSeconds(int $durationSeconds): self {
        $this->durationSeconds = $durationSeconds;
        return $this;
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function getActivityType(): ?string { return $this->activityType; }
    public function setActivityType(?string $activityType): self {
        $this->activityType = $activityType;
        return $this;
    }

    public function getFormattedDuration(): string {
        $hours = floor($this->durationSeconds / 3600);
        $minutes = floor(($this->durationSeconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m";
    }
}
