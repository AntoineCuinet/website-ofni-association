<?php

namespace App\Entity;

use App\Repository\SponsorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: SponsorRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Sponsor
{
    public const LOGO_DIR_PATH = 'pictures/sponsors/';
    public const DEFAULT_LOGO_NAME = 'logo_default.png';
    public const LOGO_PREFIX = 'logo_';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 511, nullable: true)]
    private ?string $website = null;

    #[ORM\Column]
    private ?bool $permanent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoName = null;
    private ?string $logoPath = null;
    private ?UploadedFile $logo = null;

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

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function isPermanent(): ?bool
    {
        return $this->permanent;
    }

    public function setPermanent(bool $permanent): static
    {
        $this->permanent = $permanent;

        return $this;
    }

    public function getLogoName(): ?string
    {
        return $this->logoName;
    }

    public function setLogoName(?string $logoName): static
    {
        $this->logoName = $logoName;
        $this->logoPathResolver();

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function getLogo(): ?UploadedFile
    {
        return $this->logo;
    }

    public function setLogo(?UploadedFile $logo): static
    {
        $this->logo = $logo;

        if ($logo !== null) {
            // upload image
            if ($this->logoName === null)
                $this->logoName = uniqid(Sponsor::LOGO_PREFIX) . '.' . $logo->guessClientExtension();
            $logo->move(Sponsor::LOGO_DIR_PATH, $this->logoName);
            $this->logoPathResolver();
        }
        else if (!str_ends_with($this->logoPath, Sponsor::DEFAULT_LOGO_NAME)) {
            // remove image
            unlink($this->logoPath);
            $this->setLogoName(null);
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
    public function logoPathResolver(): static
    {
        $dir_path = Sponsor::LOGO_DIR_PATH;
        if ($this->logoName === null || !file_exists($this->logoPath = $dir_path . $this->logoName)) {
            $this->logoPath = $dir_path . Sponsor::DEFAULT_LOGO_NAME;
        }

        return $this;
    }
}
