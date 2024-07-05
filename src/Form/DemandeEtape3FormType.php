<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use App\Entity\Demandes;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;


use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class DemandeEtape3FormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder

        ->add('messagerie_academique', ChoiceType::class, [
            'label' => 'Besoin d’une adresse de messagerie académique :',
            'choices' => [
                'Oui' => true,
                'Non' => false,
            ],
            'expanded' => true,
            'multiple' => false,
            'attr' => ['class' => 'form-check'],
        ])
        ->add('acces_dossiers', ChoiceType::class, [
            'label' => 'Besoin d’accès à des dossiers partagés :',
            'choices' => [
                'Oui' => true,
                'Non' => false,
            ],
            'expanded' => true,
            'multiple' => false,
            'attr' => ['class' => 'form-check'],
        ])
        ->add('liste_dossiers', TextareaType::class, [
            'label' => 'Si oui, merci de les lister :',
            'required' => false,
            'attr' => ['class' => 'form-control'],
        ])
        ->add('imprimante_print_on_demand', ChoiceType::class, [
            'label' => 'Besoin d’accéder à une imprimante autre que « PrintOnDemand » :',
            'choices' => [
                'Oui' => true,
                'Non' => false,
            ],
            'expanded' => true,
            'multiple' => false,
            'attr' => ['class' => 'form-check'],
        ])
        ->add('liste_imprimantes', TextareaType::class, [
            'label' => 'Si oui, merci de lister le ou les numéros des bureaux où se trouvent les imprimantes :',
            'required' => false,
            'attr' => ['class' => 'form-control'],
            
        ]);

    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'services' => [],
        ]);
    }
}