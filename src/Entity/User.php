<?php

/*
 * This file is part of the Nurschool project.
 *
 * (c) Nurschool <https://github.com/abbarrasa/nurschool>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Nurschool\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use Doctrine\ORM\Mapping as ORM;
use Nurschool\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity('email')]
#[ApiResource(operations: [
    new Post(
        name: 'register', 
        uriTemplate: '/users/register',
        denormalizationContext: ['groups' => 'registration'],
        openapi: new Operation(
            summary: 'Register an user',
            description: 'Register an user in Nurschool'            
        ) 
    ),
    new Post(),
    new Put()
])]
final class User
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private UuidInterface $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank()]
    #[Groups(['registration'])]
    private string $firstname;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank()]    
    #[Groups(['registration'])]
    private string $lastname;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank()]    
    #[Assert\Email()]
    #[Groups(['registration'])]
    private string $email;

    #[ORM\Column]
    private bool $enabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    public function __construct(?UuidInterface $id = null)
    {
        $this->id = $id ?? Uuid::uuid4();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

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
}
