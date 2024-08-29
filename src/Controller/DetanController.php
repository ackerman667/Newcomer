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

        return $this->render('assistance/demandes_validees.html.twig', [
            'demandesWithUsers' => $demandesWithUsers,
            'monApplication' => $monApplication,
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


    


}
