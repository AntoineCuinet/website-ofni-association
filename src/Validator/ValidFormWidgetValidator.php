<?php

namespace App\Validator;

use App\Entity\FormWidget;
use App\Enum\FormWidgetKind;
use App\Repository\FormWidgetRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidFormWidgetValidator extends ConstraintValidator
{
    public function __construct(private FormWidgetRepository $repository)
    {
    }

    public function validate(mixed $widget, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidFormWidget) {
            throw new UnexpectedTypeException($constraint, ValidFormWidget::class);
        }
        if (!$widget instanceof FormWidget) {
            return;
        }

        switch ($widget->getKind()) {
            case FormWidgetKind::NATIVE:
                if (!in_array($widget->getNativeType(), FormWidget::TYPES, true)) {
                    $this->violation('Type de champ invalide.');
                }

                return;
            case FormWidgetKind::LIST:
                $this->validateList($widget);
                break;
            case FormWidgetKind::COMPOSED:
                $this->validateComposed($widget);
                break;
            default:
                return;
        }

        if (null !== $widget->getId() && $this->contains($widget->getChildIds(), $widget->getId())) {
            $this->violation('Un widget ne peut pas se contenir lui-même.');
        }
    }

    private function validateList(FormWidget $list): void
    {
        $inner = ($id = $list->getChildIds()[0] ?? null) ? $this->repository->find($id) : null;
        if (null === $inner) {
            $this->violation('Choisissez le widget à répéter dans la liste.');
        } elseif ($inner->isChoiceNative() && [] === $list->getListChoices()) {
            $this->violation('Une liste déroulante doit avoir au moins une option.');
        }
        if (!in_array($list->getMinEntries(), [0, 1], true)) {
            $this->violation('Le nombre minimum d\'entrées doit être 0 ou 1.');
        }
    }

    private function validateComposed(FormWidget $composed): void
    {
        $entries = array_filter($composed->getContent(), 'is_array');
        if ([] === $entries) {
            $this->violation('Un widget composé doit contenir au moins un widget.');

            return;
        }

        $keys = [];
        foreach (array_values($entries) as $position => $entry) {
            $label = sprintf('Widget n°%d : ', $position + 1);
            $widget = isset($entry['widget']) ? $this->repository->find($entry['widget']) : null;
            if (null === $widget) {
                $this->violation($label.'choisissez un widget.');
                continue;
            }
            $name = trim((string) ($entry['name'] ?? ''));
            if ('' === $name) {
                $this->violation($label.'le nom affiché est obligatoire.');
            } elseif (mb_strlen($name) > 255) {
                $this->violation($label.'le nom affiché est trop long (255 caractères maximum).');
            }
            if ($widget->isChoiceNative() && [] === FormWidget::cleanChoices($entry['choices'] ?? [])) {
                $this->violation($label.'une liste déroulante doit avoir au moins une option.');
            }
            $key = (string) ($entry['key'] ?? '');
            if (!preg_match('/^[a-z][a-z0-9_]*$/i', $key) || in_array($key, $keys, true)) {
                $this->violation($label.'clé de réponse invalide.');
            }
            $keys[] = $key;
        }
    }

    /**
     * Whether $targetId is reachable from the given widgets.
     *
     * @param int[] $ids
     * @param int[] $visited
     */
    private function contains(array $ids, int $targetId, array &$visited = []): bool
    {
        foreach ($ids as $id) {
            if ($id === $targetId) {
                return true;
            }
            if (in_array($id, $visited, true)) {
                continue;
            }
            $visited[] = $id;
            $child = $this->repository->find($id);
            if ($child && $this->contains($child->getChildIds(), $targetId, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function violation(string $message): void
    {
        $this->context->buildViolation($message)->atPath('content')->addViolation();
    }
}
