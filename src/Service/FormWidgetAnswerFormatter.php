<?php

namespace App\Service;

use App\Entity\FormWidget;
use App\Entity\Registration;
use App\Entity\RegistrationForm;
use App\Enum\FormWidgetKind;
use App\Form\FormWidgetFieldResolver;
use App\Model\FormWidgetEntry;

/**
 * Turns registration answers (nested data of the form's widget) into text,
 * for the admin table, the CSV export and the confirmation email.
 */
class FormWidgetAnswerFormatter
{
    public function __construct(private FormWidgetFieldResolver $resolver)
    {
    }

    /**
     * One column per entry of the form's (composed) widget.
     *
     * @return FormWidgetEntry[]
     */
    public function columns(RegistrationForm $registrationForm): array
    {
        return $this->resolver->entries($registrationForm->getWidget());
    }

    public function answer(Registration $registration, FormWidgetEntry $entry): string
    {
        return $this->format($entry->widget, $registration->getAnswers()[$entry->key] ?? null);
    }

    /**
     * Native: the value. List: one entry per line. Composed: "Name : value" pairs separated by " ; ".
     */
    public function format(FormWidget $widget, mixed $value): string
    {
        // unticked boxes aren't stored
        if (FormWidget::TYPE_CHECKBOX === $widget->getNativeType()) {
            return $value ? 'Oui' : 'Non';
        }
        if (null === $value || '' === $value || [] === $value) {
            return '';
        }

        switch ($widget->getKind()) {
            case FormWidgetKind::LIST:
                $inner = $this->resolver->listInner($widget);
                if (!$inner || !is_array($value)) {
                    return '';
                }

                return implode("\n", array_filter(array_map(fn ($entry) => $this->format($inner, $entry), $value), fn (string $entry) => '' !== $entry));
            case FormWidgetKind::COMPOSED:
                if (!is_array($value)) {
                    return '';
                }
                $parts = [];
                foreach ($this->resolver->entries($widget) as $entry) {
                    $text = $this->format($entry->widget, $value[$entry->key] ?? null);
                    if ('' !== $text) {
                        $parts[] = $entry->name.' : '.$text;
                    }
                }

                return implode(' ; ', $parts);
            default:
                if (FormWidget::TYPE_DATE === $widget->getNativeType() && is_string($value) && ($date = \DateTime::createFromFormat('!Y-m-d', $value))) {
                    return $date->format('d/m/Y');
                }

                return is_scalar($value) ? (string) $value : '';
        }
    }
}
