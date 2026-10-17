<?php

namespace App\Form;

use App\Entity\FormWidget;
use App\Enum\FormWidgetKind;
use App\Model\FormWidgetEntry;
use App\Repository\FormWidgetRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Admin editor of a list or composed widget (natives are fixed). Each kind has
 * its own unmapped fields, shown by public/js/form-widget-builder.js and written
 * to the widget's content.
 */
class FormWidgetAdminType extends AbstractType
{
    public function __construct(private FormWidgetRepository $repository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FormWidget|null $self */
        $self = $builder->getData();
        $selfId = $self?->getId();

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom dans la bibliothèque (visible uniquement par les admins)',
                'empty_data' => '',
            ])
            // the kind can't change once created: stored answers depend on it
            ->add('kind', EnumType::class, [
                'label' => 'Type de widget',
                'class' => FormWidgetKind::class,
                'choices' => [FormWidgetKind::COMPOSED, FormWidgetKind::LIST],
                'choice_label' => fn (FormWidgetKind $kind) => $kind->label(),
                'disabled' => null !== $selfId,
                'attr' => ['data-widget-kind' => true],
            ])

            // LIST
            ->add('listWidget', EntityType::class, [
                'label' => 'Widget répété',
                'mapped' => false,
                'required' => false,
                'class' => FormWidget::class,
                'choice_label' => 'name',
                'group_by' => fn (FormWidget $widget) => $widget->isNative() ? 'Champs de base' : $widget->getKind()->label().'s',
                'choice_attr' => fn (FormWidget $widget) => ['data-native-type' => $widget->getNativeType() ?? ''],
                'query_builder' => fn (EntityRepository $repository) => $selfId
                    ? $repository->createQueryBuilder('w')->andWhere('w.id != :id')->setParameter('id', $selfId)->orderBy('w.kind', 'DESC')->addOrderBy('w.id', 'ASC')
                    : $repository->createQueryBuilder('w')->orderBy('w.kind', 'DESC')->addOrderBy('w.id', 'ASC'),
                'placeholder' => 'Choisir un widget…',
                'row_attr' => ['data-widget-section' => FormWidgetKind::LIST->value],
                'attr' => ['data-list-widget' => true],
            ])
            ->add('listChoices', TextareaType::class, [
                'label' => 'Options de la liste déroulante (une par ligne)',
                'mapped' => false,
                'required' => false,
                'row_attr' => ['data-widget-section' => FormWidgetKind::LIST->value, 'data-list-choices' => true],
            ])
            ->add('atLeastOne', CheckboxType::class, [
                'label' => 'Au moins une entrée obligatoire',
                'mapped' => false,
                'required' => false,
                'row_attr' => ['data-widget-section' => FormWidgetKind::LIST->value],
            ])

            // COMPOSED: rows are renumbered in display order by the JS
            ->add('entries', CollectionType::class, [
                'label' => false,
                'mapped' => false,
                'entry_type' => FormWidgetEntryType::class,
                'entry_options' => ['excluded_id' => $selfId],
                'allow_add' => true,
                'allow_delete' => true,
                // a row without widget nor name is an empty row
                'delete_empty' => fn (?array $entry) => null === ($entry['widget'] ?? null) && '' === trim($entry['name'] ?? ''),
                'prototype' => true,
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn'],
            ])
        ;

        $builder->get('listChoices')->addModelTransformer(FormWidgetEntryType::choicesTransformer());

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            /** @var FormWidget $widget */
            $widget = $event->getData();
            $form = $event->getForm();
            $content = $widget->getContent();

            switch ($widget->getKind()) {
                case FormWidgetKind::LIST:
                    $form->get('listWidget')->setData(isset($content['widget']) ? $this->repository->find($content['widget']) : null);
                    $form->get('listChoices')->setData($widget->getListChoices());
                    $form->get('atLeastOne')->setData($widget->getMinEntries() > 0);
                    break;
                case FormWidgetKind::COMPOSED:
                    $entries = [];
                    foreach (array_filter($content, 'is_array') as $entry) {
                        $entries[] = [
                            'widget' => isset($entry['widget']) ? $this->repository->find($entry['widget']) : null,
                            'name' => $entry['name'] ?? '',
                            'required' => (bool) ($entry['required'] ?? false),
                            'choices' => $entry['choices'] ?? [],
                            'key' => $entry['key'] ?? null,
                        ];
                    }
                    $form->get('entries')->setData($entries);
                    break;
                default:
            }
        });

        // before validation, which checks the content (see ValidFormWidget)
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            /** @var FormWidget $widget */
            $widget = $event->getData();
            $form = $event->getForm();

            switch ($widget->getKind()) {
                case FormWidgetKind::LIST:
                    $inner = $form->get('listWidget')->getData();
                    $widget->setContent(array_filter([
                        'widget' => $inner?->getId(),
                        'min' => $form->get('atLeastOne')->getData() ? 1 : 0,
                        'choices' => $inner?->isChoiceNative() ? ($form->get('listChoices')->getData() ?: null) : null,
                    ], fn ($value) => null !== $value));
                    break;
                case FormWidgetKind::COMPOSED:
                    $content = [];
                    foreach (array_values($form->get('entries')->getData() ?? []) as $entry) {
                        /** @var FormWidget|null $child */
                        $child = $entry['widget'] ?? null;
                        $content[] = $child
                            ? FormWidgetEntry::toContent(
                                $child->getId(),
                                $entry['key'] ?: FormWidgetEntry::newKey(),
                                trim($entry['name'] ?? ''),
                                // "required" only makes sense for an input
                                $child->isNative() && ($entry['required'] ?? false),
                                $child->isChoiceNative() ? ($entry['choices'] ?? []) : [],
                            )
                            // kept so the validator reports the row
                            : ['widget' => null, 'name' => trim($entry['name'] ?? '')];
                    }
                    $widget->setContent($content);
                    break;
                default:
            }
        }, 10);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormWidget::class,
        ]);
    }
}
