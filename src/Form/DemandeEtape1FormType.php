<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class DemandeEtape1FormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('replace_someone', ChoiceType::class, [
                'label' => 'Remplacez-vous quelqu\'un ?',
                'choices' => [
                    'Oui' => 'oui',
                    'Non' => 'non',
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => ['class' => 'form-check'],
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
                'placeholder' => false, 
                'required' => false,
                'attr' => ['class' => 'form-check'],
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
            'data_class' => null,
        ]);
    }
}
