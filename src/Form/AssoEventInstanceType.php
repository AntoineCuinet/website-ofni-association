<?php

namespace App\Form;

use App\Entity\AssoEvent;
use App\Entity\AssoEventInstance;
use App\Entity\Sponsor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssoEventInstanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', options: [
                'label' => 'Nom',
            ])
            ->add('slug'    )
            ->add('date', null, [
                'widget' => 'single_text',
            ])
            ->add('parentEvent', EntityType::class, [
                'label' => 'Événement parent de l\'instance',
                'class' => AssoEvent::class,
                'choice_label' => 'name',
            ])
            ->add('sponsors', EntityType::class, [
                'class' => Sponsor::class,
                'choice_label' => 'name',
                'multiple' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AssoEventInstance::class,
        ]);
    }
}
