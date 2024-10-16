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
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DetanController extends AbstractController
{
    #[Route('formulaireldap/demandesvalidees', name: 'demandes_validees')]
    public function demandesValidees(SessionInterface $session , MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {
      
      

        $query = $entityManager->createQuery(
            'SELECT d, u, ua
             FROM App\Entity\Demandes d
             JOIN d.IDutilisateur u
             LEFT JOIN d.autreUtilisateur ua
             WHERE d.statuts = :statuts'
        )->setParameter('statuts', 'Suivi dans LEKA');
        
        $demandesWithUsers = $query->getResult();
        

        $demandesWithUsers = $query->getResult();
        dump($demandesWithUsers);
        $alertUsers = $this->getUsersWithMultipleLEKADemandes($entityManager);


        $demandesWithProvenance = [];
foreach ($demandesWithUsers as $demande) {
    $demandesWithProvenance[] = [
        'demande' => $demande,
        'provenance' => $demande->getIDutilisateur()->getProvenance(), 
    ];
}


        return $this->render('assistance/demandes_validees.html.twig', [
            'demandesWithUsers' => $demandesWithUsers,
            'monApplication' => $monApplication,
            'alertUsers' => $alertUsers,
            'demandesWithProvenance' => $demandesWithProvenance,
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
    

        $query2 = $entityManager->createQuery(
            'SELECT d, ua
             FROM App\Entity\Demandes d
             JOIN d.autreUtilisateur ua
             WHERE d.statuts = :statut AND d.AutrePersonne = true'
        )->setParameter('statut', 'Suivi dans LEKA');
    
        $demandesAutre = $query2->getResult();

        $personneCount = [];
        foreach ($demandesAutre as $demande) {
            $key = $demande->getAutreUtilisateur()->getNom() . '-' . $demande->getAutreUtilisateur()->getPrenom() . '-' . $demande->getAutreUtilisateur()->getDateDeNaissance()->format('Y-m-d');
            if (!isset($personneCount[$key])) {
                $personneCount[$key] = 0;
            }
            $personneCount[$key]++;
        }
    

        foreach ($personneCount as $key => $count) {
            if ($count > 1) {
                $alertUsers[] = $key;
            }
        }
    
        return $alertUsers;
    }
    
    
    

    


}
