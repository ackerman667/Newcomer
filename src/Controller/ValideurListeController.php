<?php



namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use App\Security\User;

use App\Classe\MonApplication;
use Doctrine\ORM\EntityManagerInterface;


class ValideurListeController extends AbstractController

{
    #[Route('/listedemandes', name: 'listedemandes')]
    public function index(MonApplication $monApplication,  EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        $uid =$user->getUid();
        $demandes = $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
        ->where('d.uid_valideur = :uid')
        ->setParameter('uid', $uid)
        ->getQuery()
        ->getResult();
        $demandesAvecInfosUtilisateur = [];
            foreach ($demandes as $demande) {
                $utilisateurDemande = $demande->getIDutilisateur();
                $nomUtilisateur = $utilisateurDemande->getNom();
                $prenomUtilisateur = $utilisateurDemande->getPrenom();
                dump($prenomUtilisateur);

                
                // Ajouter les infos de l'utilisateur à la demande
            }
        return $this->render('valideur/index.html.twig', [
            
            'demandes' => $demandes,
            "monApplication" => $monApplication,
            'nomUtilisateur' => $nomUtilisateur,
            'prenomUtilisateur' => $prenomUtilisateur,

        ]);
    }
}
