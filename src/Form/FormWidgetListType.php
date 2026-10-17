<?php

namespace App\Form;

use App\Entity\FormWidget;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

/**
 * A LIST widget: its inner widget, repeated as many times as the user wants
 * (entries are added with a "+" button, see public/js/form-widget-list.js).
 */
class FormWidgetListType extends AbstractType
{
    public function __construct(private FormWidgetFieldResolver $resolver)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // one entry is displayed by default; runs before the collection creates its children
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            if (empty($event->getData())) {
                $event->setData([null]);
            }
        }, 10);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['min'] = $options['widget']->getMinEntries();
        $view->vars['prototype_name'] = $options['prototype_name'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('widget');
        $resolver->setAllowedTypes('widget', FormWidget::class);
        $resolver->setDefaults([
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => true,
            // entries left blank are dropped, so they neither count nor get validated
            'delete_empty' => fn (mixed $entry) => FormWidgetFieldResolver::isEmptyData($entry),
            'error_bubbling' => false,
        ]);

        // entries have no label: the list has one
        $entry = fn (Options $options): ?array => ($inner = $this->resolver->listInner($options['widget']))
            ? $this->resolver->resolve($inner, false, false, $options['widget']->getListChoices())
            : null;
        $resolver->setDefault('entry_type', fn (Options $options) => $entry($options)[0] ?? null);
        $resolver->setDefault('entry_options', fn (Options $options) => $entry($options)[1] ?? []);
        // each list has its own placeholder, so nested lists don't replace each other's
        $resolver->setDefault('prototype_name', fn (Options $options) => '__w'.$options['widget']->getId().'__');
        // a list that may stay empty doesn't make its entries' inputs required in the browser
        $resolver->setDefault('required', fn (Options $options) => $options['widget']->getMinEntries() > 0);
        $resolver->setDefault('constraints', fn (Options $options) => $options['widget']->getMinEntries() > 0
            ? [new Count(min: $options['widget']->getMinEntries(), minMessage: 'Ajoutez au moins une entrée.')]
            : []);
    }

    public function getParent(): string
    {
        return CollectionType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'widget_list';
    }
}
