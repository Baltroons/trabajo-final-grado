<?php

namespace App\Entity;

use App\Repository\SalaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalaRepository::class)]
class Sala
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 100)]
    private ?string $categoria = null;

    #[ORM\ManyToOne(inversedBy: 'salasCreadas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creador = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'salasSuscritas')]
    private Collection $miembros;

    #[ORM\OneToMany(targetEntity: Mensaje::class, mappedBy: 'sala', orphanRemoval: true)]
    private Collection $mensajes;

    // CORRECCIÓN: Ahora usa Atributos de PHP 8 en lugar de comentarios
    #[ORM\OneToMany(targetEntity: Archivo::class, mappedBy: 'sala', orphanRemoval: true)]
    private Collection $archivos;

    public function __construct()
    {
        $this->miembros = new ArrayCollection();
        $this->mensajes = new ArrayCollection();
        $this->archivos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // ... (Mantén tus getters y setters de nombre, descripción y categoría igual)

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function setCategoria(string $categoria): static
    {
        $this->categoria = $categoria;
        return $this;
    }

    public function getCreador(): ?User
    {
        return $this->creador;
    }

    public function setCreador(?User $creador): static
    {
        $this->creador = $creador;
        return $this;
    }

    /** @return Collection<int, User> */
    public function getMiembros(): Collection
    {
        return $this->miembros;
    }

    public function addMiembro(User $miembro): static
    {
        if (!$this->miembros->contains($miembro)) {
            $this->miembros->add($miembro);
        }
        return $this;
    }

    public function removeMiembro(User $miembro): static
    {
        $this->miembros->removeElement($miembro);
        return $this;
    }

    /** @return Collection<int, Mensaje> */
    public function getMensajes(): Collection
    {
        return $this->mensajes;
    }

    public function addMensaje(Mensaje $mensaje): static
    {
        if (!$this->mensajes->contains($mensaje)) {
            $this->mensajes->add($mensaje);
            $mensaje->setSala($this);
        }
        return $this;
    }

    /** @return Collection<int, Archivo> */
    public function getArchivos(): Collection
    {
        return $this->archivos;
    }

    public function addArchivo(Archivo $archivo): static
    {
        if (!$this->archivos->contains($archivo)) {
            $this->archivos->add($archivo);
            $archivo->setSala($this);
        }
        return $this;
    }

    public function removeArchivo(Archivo $archivo): static
    {
        if ($this->archivos->removeElement($archivo)) {
            if ($archivo->getSala() === $this) {
                $archivo->setSala(null);
            }
        }
        return $this;
    }
}
