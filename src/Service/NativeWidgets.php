<?php

namespace App\Service;

use App\Entity\FormWidget;
use App\Enum\FormWidgetKind;
use App\Repository\FormWidgetRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The fixed native widgets, one per HTML input type (FormWidget::TYPES).
 * They are created by a migration; missing ones are recreated on first use.
 */
class NativeWidgets
{
    /** @var array<string, FormWidget>|null */
    private ?array $natives = null;

    public function __construct(private FormWidgetRepository $repository, private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, FormWidget> native widgets by type, in FormWidget::TYPES order
     */
    public function all(): array
    {
        if (null !== $this->natives && !in_array(null, array_map(fn (FormWidget $w) => $w->getId(), $this->natives), true)) {
            return $this->natives;
        }

        $existing = [];
        foreach ($this->repository->findBy(['kind' => FormWidgetKind::NATIVE], ['id' => 'ASC']) as $widget) {
            $existing[$widget->getNativeType()] ??= $widget;
        }

        $natives = [];
        $created = false;
        foreach (FormWidget::TYPES as $label => $type) {
            if (!isset($existing[$type])) {
                $existing[$type] = (new FormWidget())
                    ->setName($label)
                    ->setKind(FormWidgetKind::NATIVE)
                    ->setContent(['type' => $type]);
                $this->em->persist($existing[$type]);
                $created = true;
            }
            $natives[$type] = $existing[$type];
        }
        if ($created) {
            $this->em->flush();
        }

        return $this->natives = $natives;
    }

    public function get(string $type): FormWidget
    {
        return $this->all()[$type] ?? throw new \InvalidArgumentException("Unknown native widget type \"$type\".");
    }
}
