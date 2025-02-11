<?php

namespace App\Form;

use App\Entity\Sponsor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SponsorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];
        $submitLabel = $isEdit ? 'Mettre à jour' : 'Ajouter';
        $builder
            ->add('name', options: ['label' => 'Nom'])
            ->add('website', options: ['label' => 'Site web'])
            ->add('permanent', options: ['label' => 'Sponsor permanent ?'])
        ;
        if (!$isEdit) {
            // add image field for creation mode
            $builder->add('logo', FileType::class, options: [
                'label' => 'Logo',
                'required' => false
            ]);
        }
        $builder->add('submit', SubmitType::class, options: [
            'label' => $submitLabel,
            'attr' => ['class' => 'btn'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sponsor::class,
            'is_edit' => false,
        ]);
    }
}
