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
use App\Entity\HistoriqueDemande;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DetanController extends AbstractController



{




    /**
 * @brief Affiche toutes les demandes ayant le statut "Suivi dans LEKA".
 *
 * Cette méthode récupère toutes les demandes validées et les affiche dans une interface,
 * avec des informations sur les utilisateurs associés et des alertes pour ceux ayant plusieurs demandes.
 *
 * @Route('formulaireldap/demandesvalidees', name='demandes_validees')
 *
 * @param SessionInterface $session La session utilisateur.
 * @param MonApplication $monApplication Informations sur l'application.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return Response La page listant les demandes validées.
 *
 * @details
 * - Récupère toutes les demandes avec le statut "Suivi dans LEKA".
 * - Permet d'associer un compte externe à un compte académique grace à l'UID
 * - Génère une alerte pour les utilisateurs ayant plusieurs demandes (Vérification du nombre de demandes sur le nom et le prénom).
 *
 */

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
        // dump($demandesWithUsers);
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




/**
 * @brief Affiche l'historique des modifications d'une demande.
 *
 * Cette méthode permet de visualiser toutes les modifications apportées à une demande donnée,
 * y compris les informations sur chaque étape du processus.
 *
 * @Route('formulaireldap/voirhistorique/{id}', name='historique', methods=['GET', 'POST'])
 *
 * @param Request $request La requête HTTP courante.
 * @param MonApplication $monApplication Informations sur l'application.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param UserRepository $userRepository Dépôt pour accéder aux utilisateurs.
 * @param int $id Identifiant de la demande.
 *
 * @return Response La page affichant l'historique de la demande.
 *
 * @throws NotFoundHttpException Si la demande est introuvable.
 */

    #[Route('formulaireldap/voirhistorique/{id}', name: 'historique', methods: ['GET','POST'])]
    public function voirHistorique(Request $request,  MonApplication $monApplication ,EntityManagerInterface $entityManager, UserRepository $userRepository, $id): Response
    
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        // $historiques = $demande->getHistoriques();

    $historique = $entityManager->getRepository(HistoriqueDemande::class)->findBy(['demande' => $id]);

       

        // return $this->redirectToRoute('demandes_validees');

        return $this->render('assistance/historique.html.twig', [
            'demande' => $demande,
            'historique' => $historique,
            'monApplication' => $monApplication,
        ]);
    }



    /**
 * @brief Supprime l'UID d'un utilisateur spécifique.
 *
 * Cette méthode permet de dissocier un UID d'un utilisateur en le mettant à `null` dans la base de données SI ET SEULEMENT SI C'est un compte externe à l'origine de la demande.
 *
 * @Route('/supprimer_uid/{id}', name='supprimer_uid', methods=['POST'])
 *
 * @param Request $request La requête HTTP courante.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param UserRepository $userRepository Dépôt pour accéder aux utilisateurs.
 * @param int $id Identifiant de l'utilisateur.
 *
 * @return Response Une redirection vers la liste des demandes validées.
 *
 * @details
 * - Si l'utilisateur est introuvable, affiche un message d'erreur.
 * - Met à jour l'utilisateur et persiste la modification en base de données.
 */

    #[Route('/supprimer_uid/{id}', name: 'supprimer_uid', methods: ['POST'])]
public function supprimerUid(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, $id): Response
{
    $user = $userRepository->find($id);

    if ($user) {
        // Supprimer l'UID en le mettant à null
        $user->setUid(null);
        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'UID supprimé avec succès.');
    } else {
        $this->addFlash('error', 'Utilisateur non trouvé.');
    }

    return $this->redirectToRoute('demandes_validees');
}










/**
 * @brief Ajoute ou met à jour l'UID d'un utilisateur.
 *
 * Cette méthode permet d'associer un UID à un utilisateur dans la base de données.
 *
 * @Route('/ajouter_uid/{id}', name='ajouter_uid', methods=['POST'])
 *
 * @param Request $request La requête HTTP courante contenant l'UID à ajouter.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param UserRepository $userRepository Dépôt pour accéder aux utilisateurs.
 * @param int $id Identifiant de l'utilisateur.
 *
 * @return Response Une redirection vers la liste des demandes validées.
 *
 * @details
 * - Si l'utilisateur ou l'UID est invalide, affiche un message d'erreur.
 * - Persiste l'UID pour l'utilisateur en base de données et confirme l'opération.
 */

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





    /**
 * @brief Identifie les utilisateurs ayant plusieurs demandes .
 *
 * Cette méthode permet de détecter les utilisateurs (ou personnes associées) ayant
 * soumis plusieurs demandes avec le statut "Suivi dans LEKA".
 *
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return array Retourne un tableau contenant les identifiants des utilisateurs concernés.
 *
 * @details
 * - Les utilisateurs ayant plusieurs demandes directement liées à leur compte sont identifiés.
 * - Les personnes associées à plusieurs demandes (via `autreUtilisateur`) sont également détectées.
 * - Utilise des requêtes DQL pour regrouper et compter les demandes par utilisateur ou personne associée.
 *
 * Exemple d'utilisation :
 * ```php
 * $alertUsers = $this->getUsersWithMultipleLEKADemandes($entityManager);
 * foreach ($alertUsers as $userId) {
 *     // Gérer chaque utilisateur avec des demandes multiples
 * }
 * ```
 */

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
