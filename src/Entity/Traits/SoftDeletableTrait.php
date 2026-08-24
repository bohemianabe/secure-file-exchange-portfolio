<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;


// Example trait for entities
trait SoftDeletableTrait
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function delete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }
    public function restore(): void
    {
        $this->deletedAt = null;
    }
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }
    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
