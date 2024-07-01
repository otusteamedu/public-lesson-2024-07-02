<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: '`order`')]
#[ORM\Entity]
class Order
{
    #[ORM\Column(type: 'bigint', unique: true, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $state;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isCollected = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function isCollected(): bool
    {
        return $this->isCollected ?? false;
    }

    public function setIsCollected(bool $isCollected): void
    {
        $this->isCollected = $isCollected;
    }
}
