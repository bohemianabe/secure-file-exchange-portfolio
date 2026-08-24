<?php

namespace App\Entity;

use App\Repository\CountriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountriesRepository::class)]
class Countries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_country', type: 'integer')]
    private ?int $idCountry = null;

    #[ORM\Column(length: 2, unique: true)]
    private ?string $code = null; // e.g. "US"

    #[ORM\Column(length: 64, unique: true)]
    private ?string $name = null; // e.g. "United States"

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: States::class, cascade: ['remove'], orphanRemoval: true)]
    private ?Collection $states = null;

    #[ORM\Column(name: 'uses_state_dropdown', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $usesStateDropdown = true;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Firms::class)]
    private Collection $firms;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Clients::class)]
    private Collection $clients;

    public function __construct()
    {
        $this->firms = new ArrayCollection();
        $this->clients = new ArrayCollection();
        $this->states = new ArrayCollection();
    }

    public function hasFirms(): bool
    {
        return $this->firms instanceof Collection && !$this->firms->isEmpty();
    }

    public function hasClients(): bool
    {
        return $this->clients instanceof Collection && !$this->clients->isEmpty();
    }

    public function hasStates(): bool
    {
        return $this->states instanceof Collection && !$this->states->isEmpty();
    }

    public function getFirms(): Collection
    {
        return new ArrayCollection($this->hasFirms() ? $this->firms->toArray() : []);
    }

    public function getClients(): Collection
    {
        return new ArrayCollection($this->hasClients() ? $this->clients->toArray() : []);
    }

    public function getStates(): Collection
    {
        return new ArrayCollection($this->hasStates() ? $this->states->toArray() : []);
    }

    public function getId(): ?int
    {
        return $this->idCountry;
    }

    public function getIdCountry(): ?int
    {
        return $this->idCountry;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUsesStateDropdown(): ?bool
    {
        return $this->usesStateDropdown;
    }

    public function setUsesStateDropdown(bool $usesStateDropdown): self
    {
        $this->usesStateDropdown = $usesStateDropdown;
        return $this;
    }

    // public function __toString(): string
    // {
    //     return $this->name ?? '';
    // }
}
