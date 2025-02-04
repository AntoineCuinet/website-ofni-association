<?php

namespace App\Form;

use App\Entity\Board;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BoardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // TODO: faire en sorte que year_start < year_end.
        // et que l'on ait un widget select avec les années comme dans la gallerie
        $builder
            ->add('year_start')
            ->add('year_end')
            ->add('president')
            ->add('tresorier')
            ->add('secretaire')
            //->add('others')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Board::class,
        ]);
    }
}
