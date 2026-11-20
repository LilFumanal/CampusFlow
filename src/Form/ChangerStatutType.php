<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangerStatutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statut', ChoiceType::class, [
                'label' => 'Nouveau statut',
                'choices' => [
                    'Acceptée' => 'Acceptée',
                    'Refusée' => 'Refusée',
                    'En cours' => 'En cours',
                    'En attente' => 'En attente',
                    'Traitée' => 'Traitée',
                ],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire (facultatif)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
