<?php

namespace Nurschool\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'UNIQ_SOCIAL_SUBJECT', fields: ['provider', 'subject'])]
class SocialIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 20)] private string $provider,
        #[ORM\Column(length: 255)] private string $subject,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $user,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
