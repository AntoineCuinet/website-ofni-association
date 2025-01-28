<?php

namespace App\Form;

use App\Entity\AssoEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssoEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', options: [
                'label' => 'Nom'
            ])
            ->add('slug')
            ->add('description')
            ->add('descriptionMini', options: [
                'label' => 'Résumé'
            ])
        ;
        // create mode
        if (!$options['is_edit']) {
            $builder
                ->add('image', FileType::class, options: [
                    'label' => 'Image',
                    'required' => false
                ])
                ->add('submit', SubmitType::class, options: [
                    'label' => 'Créer',
                    'attr' => [
                        'class' => 'btn'
                    ]
                ])
            ;
        }
        // edit mode
        else {
            $builder
                ->add('submit', SubmitType::class, options: [
                    'label' => 'Mettre à jour',
                    'attr' => [
                        'class' => 'btn'
                    ]
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AssoEvent::class,
            'is_edit' => false
        ]);
    }
}
