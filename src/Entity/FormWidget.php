<?php

namespace App\Entity;

use App\Enum\FormWidgetKind;
use App\Repository\FormWidgetRepository;
use App\Validator\ValidFormWidget;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A piece of form. Its content depends on its kind:
 *  - NATIVE:   {type: <one of TYPES>}, a plain HTML input; natives are fixed (see NativeWidgets)
 *  - LIST:     {widget: <id>, min: 0|1, choices?: string[]}, the inner widget repeated as many
 *              times as the user wants (choices when it repeats a dropdown)
 *  - COMPOSED: [{widget: <id>, key: string, name: string, required: bool, choices?: string[]}, ...],
 *              its entries in display order; "name" is what users see, "key" the stable answer key
 *
 * The widget's own name is only shown to admins (the fixed label of a native).
 */
#[ORM\Entity(repositoryClass: FormWidgetRepository::class)]
#[ValidFormWidget]
class FormWidget
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_EMAIL = 'email';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_CHOICE = 'choice';
    public const TYPE_TEL = 'tel';

    /** Native label => native type */
    public const TYPES = [
        'Texte court' => self::TYPE_TEXT,
        'Texte long' => self::TYPE_TEXTAREA,
        'Email' => self::TYPE_EMAIL,
        'Nombre' => self::TYPE_NUMBER,
        'Date' => self::TYPE_DATE,
        'Case à cocher' => self::TYPE_CHECKBOX,
        'Liste déroulante' => self::TYPE_CHOICE,
        'Téléphone' => self::TYPE_TEL,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du widget est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(enumType: FormWidgetKind::class)]
    #[Assert\NotNull(message: 'Le type de widget est obligatoire.')]
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

    public function setName(?string $name): static
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

    public function isNative(): bool
    {
        return FormWidgetKind::NATIVE === $this->kind;
    }

    /**
     * Ids of the widgets this one is made of (the inner widget of a list, the entries of a composed one).
     *
     * @return int[]
     */
    public function getChildIds(): array
    {
        return match ($this->kind) {
            FormWidgetKind::LIST => isset($this->content['widget']) ? [(int) $this->content['widget']] : [],
            FormWidgetKind::COMPOSED => array_values(array_map(
                fn (array $entry) => (int) $entry['widget'],
                array_filter($this->content, fn ($entry) => is_array($entry) && isset($entry['widget']))
            )),
            default => [],
        };
    }

    // NATIVE helpers

    public function getNativeType(): ?string
    {
        return $this->content['type'] ?? null;
    }

    public function isChoiceNative(): bool
    {
        return $this->isNative() && self::TYPE_CHOICE === $this->getNativeType();
    }

    // LIST helpers

    /**
     * Minimum number of entries of a list: 0 (may be empty) or 1.
     */
    public function getMinEntries(): int
    {
        return (int) ($this->content['min'] ?? 0);
    }

    /**
     * @return string[] options of the repeated dropdown
     */
    public function getListChoices(): array
    {
        return self::cleanChoices($this->content['choices'] ?? []);
    }

    /**
     * @return string[] trimmed, non-empty options
     */
    public static function cleanChoices(array $choices): array
    {
        return array_values(array_filter(
            array_map(fn ($choice) => trim((string) $choice), $choices),
            fn (string $choice) => '' !== $choice
        ));
    }
}
