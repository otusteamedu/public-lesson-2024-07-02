<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: '`order`')]
#[ORM\Entity]
class Order
{
    #[ORM\Column(type: 'bigint', unique: true, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $state;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isCollected = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private DateTimeInterface $deliveredAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getState(): ?array
    {
        return $this->state;
    }

    public function setState(array $state): void
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

    public function getDeliveredAt(): DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(DateTimeInterface $deliveredAt): void
    {
        $this->deliveredAt = $deliveredAt;
    }
}
