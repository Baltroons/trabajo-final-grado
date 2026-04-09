<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $username = null;

    /**
     * @var Collection<int, Sala>
     */
    #[ORM\OneToMany(targetEntity: Sala::class, mappedBy: 'creador')]
    private Collection $salasCreadas;

    /**
     * @var Collection<int, Sala>
     */
    #[ORM\ManyToMany(targetEntity: Sala::class, mappedBy: 'miembros')]
    private Collection $salasSuscritas;

    /**
     * @var Collection<int, Mensaje>
     */
    #[ORM\OneToMany(targetEntity: Mensaje::class, mappedBy: 'autor')]
    private Collection $mensajes;

    /**
     * @var Collection<int, Archivo>
     */
    #[ORM\OneToMany(targetEntity: Archivo::class, mappedBy: 'subidoPor')]
    private Collection $archivos;

    public function __construct()
    {
        $this->salasCreadas = new ArrayCollection();
        $this->salasSuscritas = new ArrayCollection();
        $this->mensajes = new ArrayCollection();
        $this->archivos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return Collection<int, Sala>
     */
    public function getSalasCreadas(): Collection
    {
        return $this->salasCreadas;
    }

    public function addSalasCreada(Sala $salasCreada): static
    {
        if (!$this->salasCreadas->contains($salasCreada)) {
            $this->salasCreadas->add($salasCreada);
            $salasCreada->setCreador($this);
        }

        return $this;
    }

    public function removeSalasCreada(Sala $salasCreada): static
    {
        if ($this->salasCreadas->removeElement($salasCreada)) {
            // set the owning side to null (unless already changed)
            if ($salasCreada->getCreador() === $this) {
                $salasCreada->setCreador(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Sala>
     */
    public function getSalasSuscritas(): Collection
    {
        return $this->salasSuscritas;
    }

    public function addSalasSuscrita(Sala $salasSuscrita): static
    {
        if (!$this->salasSuscritas->contains($salasSuscrita)) {
            $this->salasSuscritas->add($salasSuscrita);
            $salasSuscrita->addMiembro($this);
        }

        return $this;
    }

    public function removeSalasSuscrita(Sala $salasSuscrita): static
    {
        if ($this->salasSuscritas->removeElement($salasSuscrita)) {
            $salasSuscrita->removeMiembro($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Mensaje>
     */
    public function getMensajes(): Collection
    {
        return $this->mensajes;
    }

    public function addMensaje(Mensaje $mensaje): static
    {
        if (!$this->mensajes->contains($mensaje)) {
            $this->mensajes->add($mensaje);
            $mensaje->setAutor($this);
        }

        return $this;
    }

    public function removeMensaje(Mensaje $mensaje): static
    {
        if ($this->mensajes->removeElement($mensaje)) {
            // set the owning side to null (unless already changed)
            if ($mensaje->getAutor() === $this) {
                $mensaje->setAutor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Archivo>
     */
    public function getArchivos(): Collection
    {
        return $this->archivos;
    }

    public function addArchivo(Archivo $archivo): static
    {
        if (!$this->archivos->contains($archivo)) {
            $this->archivos->add($archivo);
            $archivo->setSubidoPor($this);
        }

        return $this;
    }

    public function removeArchivo(Archivo $archivo): static
    {
        if ($this->archivos->removeElement($archivo)) {
            // set the owning side to null (unless already changed)
            if ($archivo->getSubidoPor() === $this) {
                $archivo->setSubidoPor(null);
            }
        }

        return $this;
    }
}
