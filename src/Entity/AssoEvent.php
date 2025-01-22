<?php

namespace App\Entity;

use App\Repository\AssoEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssoEventRepository::class)]
class AssoEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $descriptionMini = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    /**
     * @var Collection<int, AssoEventInstance>
     */
    #[ORM\OneToMany(targetEntity: AssoEventInstance::class, mappedBy: 'parentEvent', orphanRemoval: true)]
    private Collection $instances;

    public function __construct()
    {
        $this->instances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescriptionMini(): ?string
    {
        return $this->descriptionMini;
    }

    public function setDescriptionMini(string $descriptionMini): static
    {
        $this->descriptionMini = $descriptionMini;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;

        return $this;
    }

    /**
     * @return Collection<int, AssoEventInstance>
     */
    public function getInstances(): Collection
    {
        return $this->instances;
    }

    public function addInstance(AssoEventInstance $instance): static
    {
        if (!$this->instances->contains($instance)) {
            $this->instances->add($instance);
            $instance->setParentEvent($this);
        }

        return $this;
    }

    public function removeInstance(AssoEventInstance $instance): static
    {
        if ($this->instances->removeElement($instance)) {
            // set the owning side to null (unless already changed)
            if ($instance->getParentEvent() === $this) {
                $instance->setParentEvent(null);
            }
        }

        return $this;
    }
}
