<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class DemandeEtape3FormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Initialisation des choix pour les dossiers partagés
        $choices = ['Besoin Numéro téléphone Bureau?' => 'Num Tel'];

        // Ajout dynamique des ressources partagées
        foreach ($options['dossiers_partages'] as $dossier) {
            $choices[$dossier] = $dossier;
        }

        $builder
            ->add('dossiers_partages', ChoiceType::class, [
                'choices' => $choices,            // Liste des ressources
                'multiple' => true,              // Permet de sélectionner plusieurs options
                'expanded' => true,              // Affiche les choix en cases à cocher
                'label' => false,                // Pas besoin de label spécifique
                'data' => $options['dossiers_selectionnes'], // Pré-cocher les ressources déjà sélectionnées
            ])
            ->add('id_ressources', HiddenType::class, [
                'mapped' => false, // Non mappé directement sur l'entité, mais récupéré dans le contrôleur
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,               // Permet de gérer les données en tableau
            'dossiers_partages' => [],          // Liste des ressources disponibles
            'dossiers_selectionnes' => [],      // Liste des ressources sélectionnées pour modification
        ]);
    }
}
