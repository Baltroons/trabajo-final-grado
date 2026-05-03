<?php

namespace App\Entity;

use App\Repository\MensajeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MensajeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Mensaje
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)] // Permitir null si solo hay archivo
    private ?string $contenido = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $fechaCreacion = null;

    #[ORM\ManyToOne(inversedBy: 'mensajes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $autor = null;

    #[ORM\ManyToOne(inversedBy: 'mensajes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sala $sala = null;

    // --- FALTABA ESTA PROPIEDAD PARA LOS MENSAJES PRIVADOS ---
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $receptor = null;

    // --- NUEVOS CAMPOS PARA ARCHIVOS ---
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $archivoUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $archivoNombre = null;

    #[ORM\PrePersist]
    public function setFechaCreacionValue(): void {
        if ($this->fechaCreacion === null) {
            $this->fechaCreacion = new \DateTimeImmutable();
        }
    }

    // --- EL TO_ARRAY ACTUALIZADO PARA LAS NOTIFICACIONES ---
    public function toArray(): array {
        return [
            'id' => $this->id,
            'contenido' => $this->contenido,
            'autor' => $this->autor ? $this->autor->getUsername() : 'Anónimo',
            'autorId' => $this->autor ? $this->autor->getId() : null,
            'fecha' => $this->fechaCreacion ? $this->fechaCreacion->format('H:i') : date('H:i'),
            'salaId' => $this->sala ? $this->sala->getId() : null,
            'salaNombre' => $this->sala ? $this->sala->getNombre() : null,
            'archivoUrl' => $this->archivoUrl,
            'archivoNombre' => $this->archivoNombre,
        ];
    }

    // --- GETTERS Y SETTERS ---

    public function getId(): ?int { return $this->id; }

    public function getContenido(): ?string { return $this->contenido; }

    public function setContenido(?string $contenido): static
    {
        $this->contenido = $contenido;
        return $this;
    }

    public function getFechaCreacion(): ?\DateTimeImmutable { return $this->fechaCreacion; }

    public function setFechaCreacion(\DateTimeImmutable $fechaCreacion): static
    {
        $this->fechaCreacion = $fechaCreacion;
        return $this;
    }

    public function getAutor(): ?User { return $this->autor; }

    public function setAutor(?User $autor): static
    {
        $this->autor = $autor;
        return $this;
    }

    public function getSala(): ?Sala { return $this->sala; }

    public function setSala(?Sala $sala): static
    {
        $this->sala = $sala;
        return $this;
    }

    public function getReceptor(): ?User
    {
        return $this->receptor;
    }

    public function setReceptor(?User $receptor): static
    {
        $this->receptor = $receptor;
        return $this;
    }

    public function getArchivoUrl(): ?string {
        return $this->archivoUrl;
    }

    public function setArchivoUrl(?string $url): self {
        $this->archivoUrl = $url;
        return $this;
    }

    public function getArchivoNombre(): ?string {
        return $this->archivoNombre;
    }

    public function setArchivoNombre(?string $nombre): self {
        $this->archivoNombre = $nombre;
        return $this;
    }
}
