<?php

namespace App\Form;

use App\Entity\FormWidget;
use App\Enum\FormWidgetKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form a user fills in, generated from a FormWidget.
 * A composed widget becomes one child per entry (recursively), named by the
 * entry's key and labelled with the entry's name; any other widget a single
 * "widget" child (used for previews).
 */
class FormWidgetViewType extends AbstractType
{
    public function __construct(private FormWidgetFieldResolver $resolver)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->recursiveGeneration($builder, $options['widget']);
    }

    public function recursiveGeneration(FormBuilderInterface $builder, FormWidget $formWidget): void
    {
        switch ($formWidget->getKind()) {
            case FormWidgetKind::COMPOSED:
                $this->composedGeneration($builder, $formWidget);
                break;
            default:
                [$class, $options] = $this->resolver->resolve($formWidget, $formWidget->getName());
                $builder->add('widget', $class, $options);
        }
    }

    public function composedGeneration(FormBuilderInterface $builder, FormWidget $formWidget): void
    {
        foreach ($this->resolver->entries($formWidget) as $entry) {
            [$class, $options] = $this->resolver->resolve($entry->widget, $entry->name, $entry->required, $entry->choices);
            $builder->add($entry->key, $class, $options);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('widget');
        $resolver->setAllowedTypes('widget', FormWidget::class);
        $resolver->setDefault('attr', ['class' => 'widget-composed']);
    }

    public function getBlockPrefix(): string
    {
        return 'widget_composed';
    }
}
