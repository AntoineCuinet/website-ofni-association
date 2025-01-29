<?php

namespace App\Entity;

use App\Repository\ActuRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: ActuRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Actu
{
    public const IMAGE_DIR_PATH = 'pictures/actus/';
    public const DEFAULT_IMAGE_NAME = 'actu_default.png';
    public const IMAGE_PREFIX = 'actu_';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $postedAt = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contentMini = null;

    #[ORM\Column(length: 127, nullable: true)]
    private ?string $imageName = null;
    private ?string $imagePath = null;
    private ?UploadedFile $image = null;

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

    public function getPostedAt(): ?\DateTimeImmutable
    {
        return $this->postedAt;
    }

    public function setPostedAt(\DateTimeImmutable $postedAt): static
    {
        $this->postedAt = $postedAt;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContentMini(): ?string
    {
        return $this->contentMini;
    }

    public function setContentMini(string $contentMini): static
    {
        $this->contentMini = $contentMini;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;
        $this->imagePathResolver();

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

        if ($image !== null) {
            // upload image
            if ($this->imageName === null)
                $this->imageName = uniqid(Actu::IMAGE_PREFIX) . '.' . $image->guessClientExtension();
            $image->move(Actu::IMAGE_DIR_PATH, $this->imageName);
            $this->setImageName($this->imageName);
        }
        else if (!str_ends_with($this->imagePath, Actu::DEFAULT_IMAGE_NAME)) {
            // remove image
            unlink($this->imagePath);
            $this->setImageName(null);
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
        $dir_path = Actu::IMAGE_DIR_PATH;
        if ($this->imageName === null || !file_exists($this->imagePath = $dir_path . $this->imageName)) {
            $this->imagePath = $dir_path . Actu::DEFAULT_IMAGE_NAME;
        }

        return $this;
    }
}
