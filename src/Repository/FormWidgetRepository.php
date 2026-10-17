<?php

namespace App\Repository;

use App\Entity\FormWidget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormWidget>
 */
class FormWidgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormWidget::class);
    }

    /**
     * Widgets directly made of the given one (lists repeating it, composed widgets containing it).
     *
     * @return FormWidget[]
     */
    public function findReferencing(FormWidget $widget): array
    {
        // content is JSON, filtered in PHP: the library stays small
        return array_values(array_filter(
            $this->findAll(),
            fn (FormWidget $candidate) => in_array($widget->getId(), $candidate->getChildIds(), true)
        ));
    }

    /**
     * @return FormWidget[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }
}
