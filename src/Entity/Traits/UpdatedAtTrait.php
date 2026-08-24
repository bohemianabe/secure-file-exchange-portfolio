<?php

namespace App\Entity\Traits;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;

trait UpdatedAtTrait
{
    // ag: Gedmo will automatically insert the time
    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
