<?php

namespace App\Entity;

use App\Repository\AssoEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: AssoEventRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AssoEvent
{
    public const IMAGE_DIR_PATH = 'pictures/events/';
    public const DEFAULT_IMAGE_NAME = 'event_default.png';

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
    private ?string $imagePath = null;
    private ?UploadedFile $image = null;

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

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function getImage(): ?UploadedFile
    {
        return $this->image;
    }

    public function setImage(?UploadedFile $image): static
    {
        $this->image = $image;

        // upload image
        if ($image) {
            $imageName = uniqid("event_") . '.' . $image->guessClientExtension();
            $image->move('pictures/events', $imageName);
            $this->setImageName($imageName);
        }

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

    /**
     * Resolve image name to ensure that the name refers to an existing image.
     *
     * @param ?string $imageName The image name to resolve.
     * @return string The resolved image name.
     */
    #[ORM\PostLoad]
    public function imagePathResolver(): static
    {
        $dir_path = AssoEvent::IMAGE_DIR_PATH;
        if ($this->imageName === null || !file_exists($this->imagePath = $dir_path . $this->imageName)) {
            $this->imagePath = $dir_path . AssoEvent::DEFAULT_IMAGE_NAME;
        }

        return $this;
    }
}
