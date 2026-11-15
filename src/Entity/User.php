<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface, UserInterface};

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 180)]
    private string $email;
    #[ORM\Column(length: 255)]
    private string $password = '';
    #[ORM\Column(length: 32)]
    private string $role;

    public function __construct(string $email, string $role = 'ROLE_CLIENT')
    {
        $email = strtolower(trim($email));
        if (strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse e-mail invalide.');
        }
        if (!in_array($role, ['ROLE_CLIENT', 'ROLE_GUICHETIER', 'ROLE_ADMIN'], true)) {
            throw new \InvalidArgumentException('Rôle invalide.');
        }
        $this->email = $email; $this->role = $role;
    }
    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return [$this->role, 'ROLE_USER']; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $hash): void { $this->password = $hash; }
    public function eraseCredentials(): void {}
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
        return $data;
    }
}
