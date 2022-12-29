<?php

declare(strict_types=1);

namespace Nurschool\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nurschool\Repository\UserRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 36, unique: true)]
    private string $id;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    private string $email;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $firstname;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $lastname;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;
   
    #[ORM\Column]
    private bool $enabled = false;

    public function __construct(string $email, string $firstname, string $lastname)
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->email = $this->setEmail($email);
        $this->firstname = $firstname;
        $this->lastname = $lastname;     
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        if (\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \LogicException('Invalid email');
        }

        $this->email = $email;

        return $this;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getFullName(): string
    {
        return \sprintf("%s %s", $this->firstname, $this->lastname);
    }

    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials()
    {

    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
