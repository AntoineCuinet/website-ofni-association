<?php

namespace App\Form;

use App\Entity\Actu;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', options: [
                'label' => 'Titre',
            ])
            ->add('content', options: [
                'label' => 'Contenu',
            ])
            ->add('contentMini', options: [
                'label' => 'Résumé',
            ])
            ->add('image', FileType::class, [
                'label' => 'Image',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Publier',
                'attr' => ['class' => 'btn'],
            ])
            // auto set postedAt
            ->addEventListener(FormEvents::POST_SUBMIT, $this->attachTimestamps(...))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Actu::class,
        ]);
    }

    /**
     * Attach timestamps to the Actu entity
     *
     * @param PostSubmitEvent<Actu> $event The event to handle
     * @return void
     */
    public function attachTimestamps(PostSubmitEvent $event): void
    {
        $actu = $event->getData();
        if (!($actu instanceof Actu)) {
            return;
        }
        $actu->setPostedAt(new \DateTimeImmutable());
    }
}
