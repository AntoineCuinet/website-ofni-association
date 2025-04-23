<?php

namespace App\Entity;

use App\Enum\FormWidgetKind;
use App\Repository\FormWidgetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormWidgetRepository::class)]
class FormWidget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(enumType: FormWidgetKind::class)]
    private ?FormWidgetKind $kind = null;

    #[ORM\Column]
    private array $content = [];

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

    public function getKind(): ?FormWidgetKind
    {
        return $this->kind;
    }

    public function setKind(FormWidgetKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): static
    {
        $this->content = $content;

        return $this;
    }
}
