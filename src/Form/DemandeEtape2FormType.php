<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DemandeEtape2FormType extends AbstractType

{
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    { 
        $disabledServices = explode(',', $this->params->get('disabled_services'));
        
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom :',
                'attr' => ['class' => 'form-control', 
                'maxlength' => 100],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom :',
                'attr' => ['class' => 'form-control',
                'maxlength' => 100],
            ])
            ->add('fonction', TextType::class, [
                'label' => 'Fonction :',
                'attr' => ['class' => 'form-control',
                'placeholder' => 'Max 100 caractères',
                'rows' => 6,
                'maxlength' => 100,],
            ])
            ->add('date_de_naissance', BirthdayType::class, [
                'label' => 'Date de naissance :',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('selectedService', ChoiceType::class, [
                'label' => 'Choisissez un service :',
                'choices' => $options['services'],
                'data' => $options['data']['selectedService'] ?? null,
                'required' => true,
                'choice_attr' => function ($choice, $key, $value) use ($disabledServices) {
                    
                    if (in_array($value, $disabledServices)) {
                        return ['disabled' => 'disabled'];
                    }
                    return [];
                },
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
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Max 1000 caractères',
                    'rows' => 6,
                    'maxlength' => 1000,
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Les missions ne peuvent pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])

            ->add('date_debut_contrat', DateType::class, [
                'label' => 'Date de début de contrat :',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control contract-date'],
            ])
            ->add('date_fin_contrat', DateType::class, [
                'label' => 'Date de fin de contrat :',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control contract-date'],
                'constraints' => [
                    new Callback([$this, 'validateDateRange']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email :',
                'attr' => ['class' => 'form-control',
                'maxlength' => 255],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'services' => [],
        ]);
    }

    public function validateDateRange($date_fin_contrat, ExecutionContextInterface $context)
    {
        $form = $context->getRoot();
        $date_debut_contrat = $form->get('date_debut_contrat')->getData();

        if ($date_debut_contrat && $date_fin_contrat && $date_fin_contrat < $date_debut_contrat) {
            $context->buildViolation('La date de fin de contrat ne peut pas être inférieure à la date de début de contrat.')
                ->atPath('date_fin_contrat')
                ->addViolation();
        }
    }
}
