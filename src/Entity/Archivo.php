<?php

namespace App\Entity;

use App\Repository\ArchivoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArchivoRepository::class)]
class Archivo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombreOriginal = null;

    #[ORM\Column(length: 255)]
    private ?string $nombreServidor = null;

    #[ORM\Column(length: 50)]
    private ?string $tipo = null;

    #[ORM\ManyToOne(targetEntity: Sala::class, inversedBy: 'archivos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sala $sala = null;

    #[ORM\ManyToOne(inversedBy: 'archivos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $subidoPor = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombreOriginal(): ?string
    {
        return $this->nombreOriginal;
    }

    public function setNombreOriginal(string $nombreOriginal): static
    {
        $this->nombreOriginal = $nombreOriginal;

        return $this;
    }

    public function getNombreServidor(): ?string
    {
        return $this->nombreServidor;
    }

    public function setNombreServidor(string $nombreServidor): static
    {
        $this->nombreServidor = $nombreServidor;

        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getSala(): ?Sala
    {
        return $this->sala;
    }

    public function setSala(?Sala $sala): static
    {
        $this->sala = $sala;

        return $this;
    }

    public function getSubidoPor(): ?User
    {
        return $this->subidoPor;
    }

    public function setSubidoPor(?User $subidoPor): static
    {
        $this->subidoPor = $subidoPor;

        return $this;
    }
}
