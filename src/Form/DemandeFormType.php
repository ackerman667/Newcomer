<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Demandes;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class DemandeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder

            ->add('nom', TextType::class, [
                'label' => 'Nom :',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom :',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fonction', TextType::class, [
                'label' => 'Fonction :',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Soumettre',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->add('selectedService', ChoiceType::class, [
                'label' => 'Choisissez un service :',
                'choices' => $options['services'],
                'required' => true,
            ]);
    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'services' => [],
        ]);
    }
}