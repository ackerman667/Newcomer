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
            ->add('date_de_naissance', BirthdayType::class, [
                'label' => 'Date de naissance :',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary'],
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
                'required' => false,
            ])
            ->add('date_debut_contrat', DateType::class, [
                'label' => 'Date de début de contrat :',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control contract-date',
                    // 'style' => 'display: none;',  // Hidden by default
                ],
            ])
            ->add('date_fin_contrat', DateType::class, [
                'label' => 'Date de fin de contrat :',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control contract-date',
                    // 'style' => 'display: none;',  // Hidden by default
                ],
            ])
            ->add('telephone_bureau', TextType::class, [
                'label' => 'N° de téléphone du bureau :',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
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
                
            ])
            ->add('remplacement_nom', TextType::class, [
                'label' => 'Nom :',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('remplacement_prenom', TextType::class, [
                'label' => 'Prénom :',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('telephone_avant_service', TextType::class, [
                'label' => 'Numéro de téléphone avant de quitter le service :',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('parti_rectorat', ChoiceType::class, [
                'label' => 'Parti du Rectorat :',
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => ['class' => 'form-check'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email :',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('nouvelle_affectation_service', TextType::class, [
                'label' => 'Si non, dans quel service est la nouvelle affectation :',
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