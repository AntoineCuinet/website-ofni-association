<?php

namespace App\Entity;

use App\Repository\RegistrationFieldRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RegistrationFieldRepository::class)]
class RegistrationField
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_CHOICE = 'choice';

    /** Admin dropdown labels => stored type */
    public const TYPES = [
        'Texte court' => self::TYPE_TEXT,
        'Texte long' => self::TYPE_TEXTAREA,
        'Choix unique (liste déroulante)' => self::TYPE_CHOICE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'fields')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RegistrationForm $form = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le libellé du champ est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::TYPES, message: 'Type de champ invalide.')]
    private string $type = self::TYPE_TEXT;

    #[ORM\Column]
    private bool $required = false;

    /** @var string[]|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $choices = null;

    #[ORM\Column]
    private int $position = 0;

    #[Assert\Callback]
    public function validateChoices(ExecutionContextInterface $context): void
    {
        if (self::TYPE_CHOICE === $this->type && [] === $this->getChoices()) {
            $context->buildViolation('Une liste déroulante doit avoir au moins une option.')
                ->atPath('choices')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getForm(): ?RegistrationForm
    {
        return $this->form;
    }

    public function setForm(?RegistrationForm $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * @return string[] trimmed, non-empty options
     */
    public function getChoices(): array
    {
        return array_values(array_filter(
            array_map('trim', $this->choices ?? []),
            fn (string $choice) => '' !== $choice
        ));
    }

    /**
     * @param string[]|null $choices
     */
    public function setChoices(?array $choices): static
    {
        $this->choices = $choices;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
