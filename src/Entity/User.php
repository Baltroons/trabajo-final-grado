<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Ya existe una cuenta con este email.')]
#[UniqueEntity(fields: ['username'], message: 'Este nombre de usuario ya está en uso.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $username = null;

    // --- RELACIONES DE SALAS ---

    /** @var Collection<int, Sala> */
    #[ORM\OneToMany(targetEntity: Sala::class, mappedBy: 'creador')]
    private Collection $salasCreadas;

    /** @var Collection<int, Sala> */
    #[ORM\ManyToMany(targetEntity: Sala::class, mappedBy: 'miembros')]
    private Collection $salasSuscritas;

    // --- RELACIONES DE CHAT Y ARCHIVOS ---

    /** @var Collection<int, Mensaje> */
    #[ORM\OneToMany(targetEntity: Mensaje::class, mappedBy: 'autor', orphanRemoval: true)]
    private Collection $mensajesEnviados;

    /** @var Collection<int, Mensaje> */
    #[ORM\OneToMany(targetEntity: Mensaje::class, mappedBy: 'receptor', orphanRemoval: true)]
    private Collection $mensajesRecibidos;

    /** @var Collection<int, Archivo> */
    #[ORM\OneToMany(targetEntity: Archivo::class, mappedBy: 'subidoPor')]
    private Collection $archivos;

    // --- ATRIBUTOS DE PERFIL ---

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fotoPerfil = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $biografia = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ciudad = null;

    public function __construct()
    {
        $this->salasCreadas = new ArrayCollection();
        $this->salasSuscritas = new ArrayCollection();
        $this->mensajesEnviados = new ArrayCollection();
        $this->mensajesRecibidos = new ArrayCollection();
        $this->archivos = new ArrayCollection();
    }

    // --- MÉTODOS BÁSICOS ---

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    // --- GETTERS/SETTERS DE PERFIL ---

    public function getFotoPerfil(): ?string { return $this->fotoPerfil; }
    public function setFotoPerfil(?string $fotoPerfil): static { $this->fotoPerfil = $fotoPerfil; return $this; }

    public function getBiografia(): ?string { return $this->biografia; }
    public function setBiografia(?string $biografia): static { $this->biografia = $biografia; return $this; }

    public function getCiudad(): ?string { return $this->ciudad; }
    public function setCiudad(?string $ciudad): static { $this->ciudad = $ciudad; return $this; }

    // --- GESTIÓN DE SALAS ---

    public function getSalasCreadas(): Collection { return $this->salasCreadas; }
    public function addSalasCreada(Sala $sala): static
    {
        if (!$this->salasCreadas->contains($sala)) {
            $this->salasCreadas->add($sala);
            $sala->setCreador($this);
        }
        return $this;
    }

    public function getSalasSuscritas(): Collection { return $this->salasSuscritas; }
    public function addSalasSuscrita(Sala $sala): static
    {
        if (!$this->salasSuscritas->contains($sala)) {
            $this->salasSuscritas->add($sala);
            $sala->addMiembro($this);
        }
        return $this;
    }

    // --- GESTIÓN DE MENSAJES ---

    public function getMensajesEnviados(): Collection { return $this->mensajesEnviados; }
    public function addMensajeEnviado(Mensaje $mensaje): static
    {
        if (!$this->mensajesEnviados->contains($mensaje)) {
            $this->mensajesEnviados->add($mensaje);
            $mensaje->setAutor($this);
        }
        return $this;
    }

    public function getMensajesRecibidos(): Collection { return $this->mensajesRecibidos; }
    public function addMensajeRecibido(Mensaje $mensaje): static
    {
        if (!$this->mensajesRecibidos->contains($mensaje)) {
            $this->mensajesRecibidos->add($mensaje);
            $mensaje->setReceptor($this);
        }
        return $this;
    }

    // --- GESTIÓN DE ARCHIVOS ---

    public function getArchivos(): Collection { return $this->archivos; }
    public function addArchivo(Archivo $archivo): static
    {
        if (!$this->archivos->contains($archivo)) {
            $this->archivos->add($archivo);
            $archivo->setSubidoPor($this);
        }
        return $this;
    }
}
