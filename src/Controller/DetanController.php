<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Demandes;
use App\Entity\User;

class DetanController extends AbstractController
{
    #[Route('formulaireldap/demandesvalidees', name: 'demandes_validees')]
    public function demandesValidees(MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les utilisateurs sans UID
      

        // Récupérer les demandes avec le statut "Suivi dans LEKA"
        $query = $entityManager->createQuery(
            'SELECT d, u
            FROM App\Entity\Demandes d
            JOIN d.IDutilisateur u
            WHERE d.statuts = :statuts'
            // -- AND u.uid IS NULL'
        )->setParameter('statuts', 'Suivi dans LEKA');

        $demandesWithUsers = $query->getResult();
        dump($demandesWithUsers);
        $alertUsers = $this->getUsersWithMultipleLEKADemandes($entityManager);

        return $this->render('assistance/demandes_validees.html.twig', [
            'demandesWithUsers' => $demandesWithUsers,
            'monApplication' => $monApplication,
            'alertUsers' => $alertUsers,
        ]);
    }

    #[Route('/ajouter_uid/{id}', name: 'ajouter_uid', methods: ['POST'])]
    public function ajouterUid(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, $id): Response
    {
        $uid = $request->request->get('uid');
        $user = $userRepository->find($id);

        if ($user && $uid) {
            $user->setUid($uid);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'UID ajouté avec succès.');
        } else {
            $this->addFlash('error', 'Utilisateur non trouvé ou UID invalide.');
        }

        return $this->redirectToRoute('demandes_validees');
    }







    private function getUsersWithMultipleLEKADemandes(EntityManagerInterface $entityManager): array
    {
        $alertUsers = [];
    
        // Vérification des utilisateurs ayant plusieurs demandes avec `AutrePersonne` à `false`
        $query1 = $entityManager->createQuery(
            'SELECT IDENTITY(d.IDutilisateur) as userId, COUNT(d.id) as demandeCount
             FROM App\Entity\Demandes d
             WHERE d.statuts = :statut AND d.AutrePersonne = false
             GROUP BY d.IDutilisateur
             HAVING COUNT(d.id) > 1'
        )->setParameter('statut', 'Suivi dans LEKA');
    
        $result1 = $query1->getResult();
        foreach ($result1 as $entry) {
            $alertUsers[] = $entry['userId'];
        }
    
        // Vérification pour les demandes faites par d'autres personnes (`AutrePersonne` = true)
        $query2 = $entityManager->createQuery(
            'SELECT d, ua
             FROM App\Entity\Demandes d
             JOIN d.autreUtilisateur ua
             WHERE d.statuts = :statut AND d.AutrePersonne = true'
        )->setParameter('statut', 'Suivi dans LEKA');
    
        $demandesAutre = $query2->getResult();
    
        // Compter le nombre de demandes pour les mêmes utilisateurs dans `UserAutre`
        $personneCount = [];
        foreach ($demandesAutre as $demande) {
            $key = $demande->getAutreUtilisateur()->getNom() . '-' . $demande->getAutreUtilisateur()->getPrenom() . '-' . $demande->getAutreUtilisateur()->getDateDeNaissance()->format('Y-m-d');
            if (!isset($personneCount[$key])) {
                $personneCount[$key] = 0;
            }
            $personneCount[$key]++;
        }
    
        // Ajouter les utilisateurs concernés dans l'alerte
        foreach ($personneCount as $key => $count) {
            if ($count > 1) {
                $alertUsers[] = $key;
            }
        }
    
        return $alertUsers;
    }
    
    
    

    


}
