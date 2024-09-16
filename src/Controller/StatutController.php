<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use App\Entity\Ressources;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Security\UserInformation;
use Symfony\Component\Security\Core\Security;


class StatutController extends AbstractController
{
    private $security;
  

    public function __construct(Security $security)
    {
        $this->security = $security;

    }  


    #[Route('formulaireldap/statuts', name: 'statuts_token_ldap')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {

        $user_ldap = $this->security->getUser();
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user_ldap);
        //  dump($infos_user);
        $uid_ldap = $infos_user['uid'];
        $user_bdd= $entityManager->getRepository(User::class)->findBy(['uid' => $uid_ldap]);

        $demandes = $entityManager->getRepository(Demandes::class)->findBy(['IDutilisateur' => $user_bdd]);

        // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
        //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        // }
        $query = $entityManager->createQuery(
            'SELECT u
            FROM App\Entity\User u
            WHERE u.uid = :uid
            AND u.email LIKE :email'
        )->setParameters([
            'uid' => $uid_ldap,
            'email' => '%@ac-guadeloupe.fr'
        ]);
        
        $user= $query->getOneOrNullResult();
        
        // $user = $demande->getIDutilisateur();
        // $demandes = $entityManager->getRepository(Demandes::class)->findBy(['IDutilisateur' => $user]);

        return $this->render('statuts/token_ldap.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
            'user' => $user,
        ]);
    }

    #[Route('formulaireldap/demande/consult/{token}', name: 'demande_consult_ldap')]
    public function consult(MonApplication $monApplication, $token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
        //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        // }
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
        $user = $demande->getIDutilisateur();

        return $this->render('consult/index.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'monApplication' => $monApplication,
            'ressources' => $ressources,
        ]);
    }



    #[Route('formulaireldap/demande/pdf/{token}', name: 'demande_pdf_ldap')]
    public function generatePdf($token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
        //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        // }

        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
        $user = $demande->getIDutilisateur();

        // Configurer Dompdf selon vos besoins
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        // Récupérer le contenu HTML de votre template
        $html = $this->renderView('consult/pdf.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'ressources' => $ressources,
        ]);

        // Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);

        // (Optionnel) Définir le format du papier et l'orientation
        $dompdf->setPaper('A4', 'portrait');

        // Rendre le PDF
        $dompdf->render();

        // Envoyer le PDF au navigateur
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="demande.pdf"',
        ]);
    }

    #[Route('/formulaireldap/nouvelle_demande', name: 'nouvelle_demande_ldap')]
public function nouvelleDemande(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
    // Réinitialiser les données de la session pour démarrer une nouvelle demande
    $session->remove('form_data');
    $session->remove('demande_id');

    $session->set('nouvelle_demande', true);
    

    return $this->redirectToRoute('formulaireldap_etape1');
}



}
