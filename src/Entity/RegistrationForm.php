<?php

namespace App\Entity;

use App\Repository\RegistrationFormRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RegistrationFormRepository::class)]
class RegistrationForm
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'registrationForm')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AssoEventInstance $instance = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isOpen = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $closesAt = null;

    /**
     * @var Collection<int, RegistrationField>
     */
    #[ORM\OneToMany(targetEntity: RegistrationField::class, mappedBy: 'form', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Assert\Valid]
    private Collection $fields;

    /**
     * @var Collection<int, Registration>
     */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'form', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $registrations;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
        $this->registrations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstance(): ?AssoEventInstance
    {
        return $this->instance;
    }

    public function setInstance(AssoEventInstance $instance): static
    {
        $this->instance = $instance;
        $instance->setRegistrationForm($this);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function setOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeInterface
    {
        return $this->closesAt;
    }

    public function setClosesAt(?\DateTimeInterface $closesAt): static
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    /**
     * Whether users can currently register or cancel: open and before the deadline.
     */
    public function isAcceptingRegistrations(): bool
    {
        return $this->isOpen && (null === $this->closesAt || new \DateTime() < $this->closesAt);
    }

    /**
     * @return Collection<int, RegistrationField>
     */
    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(RegistrationField $field): static
    {
        if (!$this->fields->contains($field)) {
            $this->fields->add($field);
            $field->setForm($this);
        }

        return $this;
    }

    public function removeField(RegistrationField $field): static
    {
        $this->fields->removeElement($field);

        return $this;
    }

    /**
     * @return Collection<int, Registration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }
}
