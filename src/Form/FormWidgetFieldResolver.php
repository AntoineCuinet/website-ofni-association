<?php

namespace App\Form;

use App\Entity\FormWidget;
use App\Enum\FormWidgetKind;
use App\Model\FormWidgetEntry;
use App\Repository\FormWidgetRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Maps a FormWidget to the Symfony form type (and options) that renders it.
 */
class FormWidgetFieldResolver
{
    public function __construct(private FormWidgetRepository $repository)
    {
    }

    /**
     * Whether submitted data holds nothing: null, '', false or only empty values.
     */
    public static function isEmptyData(mixed $data): bool
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if (!self::isEmptyData($value)) {
                    return false;
                }
            }

            return true;
        }

        return null === $data || '' === $data || false === $data;
    }

    /**
     * @param string|false $label    what users see (false: no label)
     * @param bool         $required only applies to native widgets
     * @param string[]     $choices  options, for a dropdown or a list of dropdowns
     *
     * @return array{0: class-string, 1: array<string, mixed>}
     */
    public function resolve(FormWidget $widget, string|false $label, bool $required = false, array $choices = []): array
    {
        return match ($widget->getKind()) {
            FormWidgetKind::LIST => [FormWidgetListType::class, ['widget' => $widget, 'label' => $label]],
            FormWidgetKind::COMPOSED => [FormWidgetViewType::class, ['widget' => $widget, 'label' => $label]],
            default => $this->resolveNative($widget, $label, $required, $choices),
        };
    }

    /**
     * Entries of a composed widget, in display order (entries of a missing widget are skipped).
     *
     * @return FormWidgetEntry[]
     */
    public function entries(FormWidget $composed): array
    {
        if (FormWidgetKind::COMPOSED !== $composed->getKind()) {
            return [];
        }

        $entries = [];
        foreach ($composed->getContent() as $entry) {
            if (!is_array($entry) || !isset($entry['widget'], $entry['key']) || !($widget = $this->repository->find($entry['widget']))) {
                continue;
            }
            $entries[] = new FormWidgetEntry(
                $widget,
                (string) $entry['key'],
                (string) ($entry['name'] ?? ''),
                (bool) ($entry['required'] ?? false),
                FormWidget::cleanChoices($entry['choices'] ?? []),
            );
        }

        return $entries;
    }

    /**
     * The widget a list repeats.
     */
    public function listInner(FormWidget $list): ?FormWidget
    {
        $id = $list->getChildIds()[0] ?? null;

        return null === $id ? null : $this->repository->find($id);
    }

    /**
     * @return array{0: class-string, 1: array<string, mixed>}
     */
    private function resolveNative(FormWidget $widget, string|false $label, bool $required, array $choices): array
    {
        $constraints = [];
        $options = [
            'label' => $label,
            'required' => $required,
        ];

        switch ($widget->getNativeType()) {
            case FormWidget::TYPE_TEXTAREA:
                $class = TextareaType::class;
                $constraints[] = new Length(max: 5000);
                break;
            case FormWidget::TYPE_EMAIL:
                $class = EmailType::class;
                $constraints[] = new Email(message: 'Adresse email invalide.');
                $constraints[] = new Length(max: 255);
                break;
            case FormWidget::TYPE_NUMBER:
                $class = NumberType::class;
                $options['html5'] = true;
                $options['invalid_message'] = 'Veuillez saisir un nombre.';
                break;
            case FormWidget::TYPE_DATE:
                $class = DateType::class;
                $options['widget'] = 'single_text';
                // stored as "Y-m-d" in the JSON answers
                $options['input'] = 'string';
                $options['invalid_message'] = 'Veuillez saisir une date valide.';
                break;
            case FormWidget::TYPE_CHECKBOX:
                $class = CheckboxType::class;
                break;
            case FormWidget::TYPE_CHOICE:
                $class = ChoiceType::class;
                $options['choices'] = array_combine($choices, $choices);
                $options['placeholder'] = 'Choisir…';
                break;
            case FormWidget::TYPE_TEL:
                $class = TelType::class;
                $constraints[] = new Regex(pattern: '/^\+?[0-9 .\-()]{6,20}$/', message: 'Numéro de téléphone invalide.');
                break;
            default:
                $class = TextType::class;
                $constraints[] = new Length(max: 255);
        }

        if ($required) {
            // a required checkbox must be ticked (e.g. "J'accepte le règlement")
            array_unshift($constraints, FormWidget::TYPE_CHECKBOX === $widget->getNativeType()
                ? new IsTrue(message: 'Cette case doit être cochée.')
                : new NotBlank(message: 'Ce champ est obligatoire.'));
        }

        return [$class, $options + ['constraints' => $constraints]];
    }
}
