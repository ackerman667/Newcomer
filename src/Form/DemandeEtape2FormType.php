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

class DemandeEtape2FormType extends AbstractType
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
        ->add('date_de_naissance', BirthdayType::class, [
            'label' => 'Date de naissance :',
            'widget' => 'single_text',
            'attr' => ['class' => 'form-control'],
            
        ])
      
        ->add('selectedService', ChoiceType::class, [
            'label' => 'Choisissez un service :',
            'choices' => $options['services'],
            'required' => true,
        ])
        ->add('statut', ChoiceType::class, [
            'label' => 'Statut :',
            'choices' => [
                'Titulaire' => 'Titulaire',
                'Contractuel' => 'Contractuel',
                'Apprenti' => 'Apprenti',
                'Service Civique' => 'Service Civique',
                'Stagiaire' => 'Stagiaire',
            ],
            'placeholder' => 'Sélectionner un statut',
            'attr' => ['class' => 'form-control'],
        ])
        ->add('missions', TextareaType::class, [
            'label' => 'Mission(s) :',
            'attr' => ['class' => 'form-control'],
            'required' => true,
        ])
        ->add('date_debut_contrat', DateType::class, [
            'label' => 'Date de début de contrat :',
            'widget' => 'single_text',
            'required' => false,
            'attr' => [
                'class' => 'form-control contract-date',
                
            ],
        ])
        ->add('date_fin_contrat', DateType::class, [
            'label' => 'Date de fin de contrat :',
            'widget' => 'single_text',
            'required' => false,
            'attr' => [
                'class' => 'form-control contract-date',
                
            ],
        ])
        ->add('email', EmailType::class, [
            'label' => 'Email :',
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