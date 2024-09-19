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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;


use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class DemandeEtape3FormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dossiers_partages', ChoiceType::class, [
                'choices' => array_combine($options['dossiers_partages'], $options['dossiers_partages']),
                'multiple' => true,
                'expanded' => true,
                'label' => false,
            ])
            ->add('global_checkbox', CheckboxType::class, [
                'label' => 'Case à cocher globale',
                'required' => false, // Pas obligatoire
            ]);
       
    }
    
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Demandes::class,
            'dossiers_partages' => [],
        ]);
    }
}