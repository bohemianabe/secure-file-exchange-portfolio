<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait UuidTrait
{
    #[ORM\Column(name: 'uuid', type: 'string', length: 36, unique: true)]
    private ?string $uuid = null;

    // public function __construct()
    // {
    //     $this->uuid = Uuid::v7();
    // }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }
}
