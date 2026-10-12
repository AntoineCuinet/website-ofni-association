<?php

namespace App\Form;

use App\Entity\FormWidget;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One entry of a composed widget, as edited by an admin: which widget,
 * the name users see, whether it is required and the options of a dropdown.
 * Data is an array {widget: FormWidget, name, required, choices, key}.
 */
class FormWidgetEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $excludedId = $options['excluded_id'];

        $builder
            ->add('widget', EntityType::class, [
                'label' => 'Widget',
                'class' => FormWidget::class,
                'choice_label' => 'name',
                'group_by' => fn (FormWidget $widget) => match (true) {
                    $widget->isNative() => 'Champs de base',
                    default => $widget->getKind()->label().'s',
                },
                // lets the JS show "Obligatoire" / "Options" only where they apply
                'choice_attr' => fn (FormWidget $widget) => ['data-native-type' => $widget->getNativeType() ?? ''],
                'query_builder' => fn (EntityRepository $repository) => $excludedId
                    ? $repository->createQueryBuilder('w')->andWhere('w.id != :id')->setParameter('id', $excludedId)->orderBy('w.kind', 'DESC')->addOrderBy('w.id', 'ASC')
                    : $repository->createQueryBuilder('w')->orderBy('w.kind', 'DESC')->addOrderBy('w.id', 'ASC'),
                'placeholder' => 'Choisir un widget…',
                'required' => false,
                'attr' => ['data-entry-widget' => true],
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom affiché aux utilisateurs',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'Obligatoire',
                'required' => false,
                'row_attr' => ['data-entry-required' => true],
            ])
            ->add('choices', TextareaType::class, [
                'label' => 'Options (une par ligne)',
                'required' => false,
                'row_attr' => ['data-entry-choices' => true],
            ])
            // stable answer key, generated for new entries
            ->add('key', HiddenType::class, [
                'required' => false,
            ])
        ;

        $builder->get('choices')->addModelTransformer(self::choicesTransformer());
    }

    /**
     * Options are edited as one per line, stored as an array.
     */
    public static function choicesTransformer(): CallbackTransformer
    {
        return new CallbackTransformer(
            fn (?array $choices): string => implode("\n", $choices ?? []),
            fn (?string $text): array => FormWidget::cleanChoices(preg_split('/\R/', $text ?? '')),
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'excluded_id' => null,
            'label' => false,
        ]);
    }
}
