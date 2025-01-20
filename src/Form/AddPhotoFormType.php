<?php

namespace App\Form;

use App\Entity\Photo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class AddPhotoFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('event')
            ->add('photos', FileType::class, [
                'mapped' => false,
                'multiple' => true,
                'constraints' => [
                    new All([
                        "constraints" => [
                            new File([
                                'maxSize' => "5M",
                                'mimeTypes' => [
                                    'image/jpeg',
                                    'image/png',
                                    'image/gif',
                                ],
                                'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, GIF).',
                            ])
                        ]
                    ])
                ]
            ])
            ->add('year', ChoiceType::class, [
                'choices' => array_combine(range(date('Y'), date('Y') - 100), range(date('Y'), date('Y') - 100)),
                'choice_label' => function ($choice, $key, $value) {
                    return $value;
                },
                'placeholder' => 'Choisissez une année',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Photo::class,
        ]);
    }
}
