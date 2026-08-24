<?php

namespace App\Entity;

use App\Repository\SignatureTemplateSignersRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignatureTemplateSignersRepository::class)]
class SignatureTemplateSigners
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_signature_template_role', type: 'integer', nullable: false)]
    private ?int $idSignatureTemaplateRole = null;

    #[ORM\Column(name: 'name', type: 'string', length: 255, nullable: false)]
    private string $name;

    #[ORM\Column(name: 'signer_order', type: 'integer', nullable: true)]
    private ?int $signerOrder = null;

    #[ORM\Column(name: 'is_cc', type: 'boolean', options: ['default' => false])]
    private bool $isCc = false;

    #[ORM\ManyToOne(targetEntity: SignatureTemplates::class, inversedBy: 'templateSigners')]
    #[ORM\JoinColumn(name: 'signature_template_id', referencedColumnName: 'id_signature_template', nullable: false, onDelete: 'CASCADE')]
    private SignatureTemplates $signatureTemplate;

    public function getId(): ?int
    {
        return $this->idSignatureTemaplateRole;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSignerOrder(): ?int
    {
        return $this->signerOrder;
    }

    public function setSignerOrder(?int $signerOrder): self
    {
        $this->signerOrder = $signerOrder;
        return $this;
    }

    public function getIsCc(): ?bool
    {
        return $this->isCc;
    }

    public function setIsCc(bool $isCC): self
    {
        $this->isCc = $isCC;

        return $this;
    }

    public function getSignatureTemplate(): ?SignatureTemplates
    {
        return $this->signatureTemplate;
    }

    public function setSignatureTemplate(SignatureTemplates $signatureTemplate): self
    {
        $this->signatureTemplate = $signatureTemplate;
        return $this;
    }
}
