<?php

namespace App\Entity;

use App\Repository\BoardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoardRepository::class)]
class Board
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $year_start = null;

    #[ORM\Column]
    private ?int $year_end = null;

    #[ORM\Column(length: 255)]
    private ?string $president = null;

    #[ORM\Column(length: 255)]
    private ?string $tresorier = null;

    #[ORM\Column(length: 255)]
    private ?string $secretaire = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $others = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYearStart(): ?int
    {
        return $this->year_start;
    }

    public function setYearStart(int $year_start): static
    {
        $this->year_start = $year_start;

        return $this;
    }

    public function getYearEnd(): ?int
    {
        return $this->year_end;
    }

    public function setYearEnd(int $year_end): static
    {
        $this->year_end = $year_end;

        return $this;
    }

    public function getPresident(): ?string
    {
        return $this->president;
    }

    public function setPresident(string $president): static
    {
        $this->president = $president;

        return $this;
    }

    public function getTresorier(): ?string
    {
        return $this->tresorier;
    }

    public function setTresorier(string $tresorier): static
    {
        $this->tresorier = $tresorier;

        return $this;
    }

    public function getSecretaire(): ?string
    {
        return $this->secretaire;
    }

    public function setSecretaire(string $secretaire): static
    {
        $this->secretaire = $secretaire;

        return $this;
    }

    public function getOthers(): ?array
    {
        return $this->others;
    }

    public function setOthers(?array $others): self
    {
        $this->others = $others;

        return $this;
    }
}
