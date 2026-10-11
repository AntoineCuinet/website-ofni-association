<?php

namespace App\Form;

use App\Entity\RegistrationField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One field of an event registration form, as edited by an admin.
 */
class EventRegistrationFieldType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'empty_data' => '',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => RegistrationField::TYPES,
                'attr' => ['data-field-type' => true],
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'Obligatoire',
                'required' => false,
            ])
            ->add('choices', TextareaType::class, [
                'label' => 'Options (une par ligne)',
                'required' => false,
                'row_attr' => ['data-field-choices' => true],
            ])
            // Rows keep their collection index so each row stays bound to the same
            // field (answers are keyed by field id); the order lives in this input.
            ->add('position', HiddenType::class, [
                'attr' => ['data-field-position' => true],
                'empty_data' => '0',
            ])
        ;

        // Options are edited as one per line, stored as an array
        $builder->get('choices')->addModelTransformer(new CallbackTransformer(
            fn (?array $choices): string => implode("\n", $choices ?? []),
            fn (?string $text): array => array_values(array_filter(
                array_map('trim', preg_split('/\R/', $text ?? '')),
                fn (string $choice) => '' !== $choice
            )),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationField::class,
        ]);
    }
}
