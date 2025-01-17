<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use App\Entity\HistoriqueDemande;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\User;
use App\Entity\TemporaryData;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\Ressources;
use Symfony\Component\HttpFoundation\Cookie; 
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;
use Dompdf\Dompdf;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Dompdf\Options;

class StatutsExterneController extends AbstractController
{

    private $timezone;
    private $requestStack;

    public function __construct(Security $security, RequestStack $requestStack)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
        
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
    }
    #[Route('formulaireext/statuts', name: 'demande_externe')]
    public function index( Request $request , SessionInterface $session, MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {
         $user = $this->getUser();

        // if (!$user) {
        //     throw $this->createNotFoundException('Utilisateur introuvable.');
        // }
        // $now = new \DateTime();
        // $expiration = $user->getTokenExpiration();
    
        // if (!$expiration || $expiration <= $now || $expiration->getTimestamp() - $now->getTimestamp() <= 1200) {
        //     // Générer un nouveau token
        //     $newToken = bin2hex(random_bytes(32));
        //     $user->setToken($newToken);
        //     $user->setTokenExpiration((new \DateTime())->modify('+24 hours'));
        //     $entityManager->flush();
    
        //     // Envoyer un e-mail avec le nouveau lien
        //     $url = $request->getSchemeAndHttpHost() . $this->generateUrl('demande_externe', ['token' => $newToken]);
    
        //     $email = (new Email())
        //         ->from('noreply@ac-guadeloupe.fr')
        //         ->to($user->getEmail())
        //         ->subject('Votre session a expiré - Nouveau lien de connexion')
        //         ->html('<p>Bonjour,</p><p>Votre session a expiré. Cliquez sur le lien suivant pour vous reconnecter : <a href="' . $url . '">' . $url . '</a></p>');
    
        //     $mailer->send($email);
    
        //     // Rediriger vers la route session_expired
        //     return $this->redirectToRoute('session_expired');
        // }

        

        // $session->clear();
        // $session->remove('form_data');
        // $session->remove('demande_id');
        // $session->remove('nouvelle_demande');
        // $session->remove('dossiers_partages');
        // $session->remove('_csrf/https-demande_etape1_form');
        // $session->remove('_csrf/https-demande_etape2_form');
        // $session->remove('_csrf/https-demande_etape3_form');
        // $session->remove('nom_service_selectionne');
        // $session->remove('nom_valideur');

     
        // if (!$session->has('externe_auth')) {
        //     $session->set('externe_auth', true);
        // }

        // if (!$session->has('externe_token')) {
        //     $session->set('externe_token', $token);
        // }
        
        // $session = $this->requestStack->getSession();
        // $session->set('externe_auth', true);
        // $session->set('externe_token', $token);

       
        // $sessionData = $session->all();

        
        // dump($sessionData);
        




       
        $demandes = $this->getDemandesPourUtilisateur($entityManager, $user);


        // dump($demandes);
        

        return $this->render('demandes/demandes_externe.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
            'user' => $user,
        ]);
    }

    #[Route('formulaireext/demande/consult/{id}', name: 'demande_consult')]
    public function consult(MonApplication $monApplication, $id, EntityManagerInterface $entityManager): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $valideur = $demande->getUidValideur();


        


      

        $user = $demande->getIDutilisateur();
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);

        return $this->render('visualiser-demandes/visualiser.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'monApplication' => $monApplication,
            'ressources' => $ressources,
            'valideur' => $valideur

        ]);
    }

    public function getDemandesPourUtilisateur(EntityManagerInterface $entityManager, User $user)
{
    $demandes = $entityManager->getRepository(Demandes::class)
    ->createQueryBuilder('d')
    ->where('d.IDutilisateur = :user')
    ->setParameter('user', $user)
    ->orderBy("CASE 
        WHEN d.statuts = 'Brouillons' THEN 1
         WHEN d.statuts = 'En attente' THEN 2
        ELSE 3 
    END", 'ASC') 
    ->addOrderBy('d.date', 'DESC') 
    ->addOrderBy('d.heureSoumission', 'DESC') 
    ->getQuery()
    ->getResult();


    return $demandes;
}

  

    #[Route('formulaireext/demande/pdf/{id}', name: 'demande_pdf')]
    public function generatePdf(/*Demandes $demande , */MonApplication $monApplication, $id, EntityManagerInterface $entityManager): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        
        // $token = $demande->getToken();
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        $user = $demande->getIDutilisateur();
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
    
        $valideur = $demande->getUidValideur();
    
        // Encoder l'image en base64
        $imagePath = $this->getParameter('kernel.project_dir') . '/public/interfaceappli/css/images/10_logoAC_GUADELOUPE_web.png';
        
        // $imagePath = 'C:\Users\nbarbeu\clonenewcomer\newcomer\public\interfaceappli\css\images\10_logoAC_GUADELOUPE_web.png';
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageSrc = 'data:image/png;base64,' . $imageData;
    
        // Configurer Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
    
        
        $html = $this->renderView('visualiser-demandes/pdf_externe.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'ressources' => $ressources,
            'monApplication' => $monApplication,
            'imageSrc' => $imageSrc,
            'valideur' => $valideur, 
    
        ]);
    
       
        $dompdf->loadHtml($html);
    
        $dompdf->setPaper('A4', 'portrait');
    
        // Rendre le PDF
        $dompdf->render();
    
        // Envoyer le PDF au navigateur
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="demande.pdf"',
        ]);
    }
    
    


    #[Route('formulaireext/nouvelle_demande', name: 'nouvelle_demande')]
    public function nouvelleDemande(EntityManagerInterface $entityManager ): Response
    {

    

         $user = $this->getUser();
        
        $temporaryData = new TemporaryData();
        $temporaryData->setUser($user);
        // $temporaryData->setData($data);
        $temporaryData->setAction('create'); 
        $temporaryData->setData([]); 
        $temporaryData->setExpiration((new \DateTime())->modify('+1 minutes'));
        $entityManager->persist($temporaryData);
        $entityManager->flush();



    
        
        return $this->redirectToRoute('formulaireexterne_etape1', [
            'uuid' => $temporaryData->getToken(),
        ]);
        
    }
    

    #[Route('formulaireext/supprimer/{id}', name: 'formulaireexterne_supprimer')]
    public function supprimerDemande(Request $request, EntityManagerInterface $entityManager, $id): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        $id_user = $demande->getIDutilisateur();
        $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
        $token = $user->getToken();


        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $historiques = $entityManager->getRepository(HistoriqueDemande::class)->findBy(['demande' => $demande]);
        foreach ($historiques as $historique) {
            $entityManager->remove($historique);
        }

        $ressources = $entityManager->getRepository(Ressources::class)->findBy(['demande' => $demande]);
        foreach ($ressources as $ressource) {
            $entityManager->remove($ressource);
        }

        $entityManager->remove($demande);
        $entityManager->flush();
        $this->addFlash('success', 'Votre demande a été supprimée.');

        return $this->redirectToRoute('demande_externe'); 
    }

    #[Route('formulaireext/modifier/{id}', name: 'modifier_demandes')]
    public function modifierDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id, MailerInterface $mailer): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $user = $demande->getIDutilisateur();
        $token = $demande->getToken();

        $data = [
            'demande_id' => $id,
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getEmail(),
            'date_de_naissance' => $user->getDateDeNaissance(),
            'fonction' => $user->getFonction(),
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'date_debut_contrat' => $user->getDateDebut(),
            'date_fin_contrat' => $user->getDateFin(),
            'statut' => $user->getStatutPersonne(),
            'missions' => $demande->getMissions(),
            
        ];

        //  $user = $this->getUser();
        $temporaryData = new TemporaryData();
        $temporaryData->setUser($user);
        $temporaryData->setAction('modifier'); 
        $temporaryData->setData($data);
        $temporaryData->setExpiration((new \DateTime())->modify('+30 minutes'));
        $entityManager->persist($temporaryData);
        $entityManager->flush();

        return $this->redirectToRoute('formulaireexterne_etape1', [
            'uuid' => $temporaryData->getToken(),
        ]);
        
    }

    #[Route('formulaireext/valider/{id}', name: 'valider_demandes')]
    public function validerDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id,MailerInterface $mailer): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $token = $demande->getToken();
        $id_user = $demande->getIDutilisateur();
        $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
        $token= $user->getToken();
        

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $demande->setDate((new \DateTime('now', $this->timezone)));
        $demande->setHeureSoumission((new \DateTime('now', $this->timezone)));
        $demande->setStatuts('En attente');
        $entityManager->persist($demande);
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
                $historique->setStatut('Envoyée');
                
                $historique->setDate(new \DateTime('now', $this->timezone));
                $historique->setStatutOperation('Envoie de la demande');

                $entityManager->persist($historique);
        $entityManager->flush();

        
        $email = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($user->getEmail())
            ->subject('Vous avez envoyé la demande.')
            ->text('Vous avez envoyé la demande.')
            ->html('<p>Bonjour, votre demande a bien été envoyée à votre chef de service.</p>');
    
     
    
            $mailer->send($email);
    
       

        return $this->redirectToRoute('demande_externe');
    }

    private function checkUserPermissionForDemande(int $demandeId, EntityManagerInterface $entityManager): void
{
    // Récupérer l'utilisateur connecté
    $currentUser = $this->getUser();

    if (!$currentUser) {
        throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette demande.');
    }
    $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

    // Vérifier si l'utilisateur connecté correspond à l'utilisateur lié à la demande
    if ($demande->getIDutilisateur() !== $currentUser) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette demande.');
    }
}



    // #[Route('/logout', name: 'app_logout')]
    // public function logout(EntityManagerInterface $entityManager, MailerInterface $mailer, RequestStack $requestStack, UrlGeneratorInterface $urlGenerator): Response
    // {
    //     $session = $requestStack->getSession();
    
    //     // Vérifiez si un token est présent
    //     $token = $session->get('externe_token');
    //     if ($token) {
    //         // Trouver l'utilisateur correspondant au token
    //          $user = $this->getUser();
    
    //         if ($user) {

    //             $temporaryDataEntries = $entityManager->getRepository(\App\Entity\TemporaryData::class)
    //             ->findBy(['user' => $user]);

    //         foreach ($temporaryDataEntries as $entry) {
    //             $entityManager->remove($entry);
    //         }
    //         $entityManager->flush();

    //             // Générer un nouveau token
    //             $newToken = bin2hex(random_bytes(32));
    //             $user->setToken($newToken);
    //             $entityManager->flush();
    
    //             // Envoyer un email avec le nouveau token
    //             $url = $urlGenerator->generate('demande_externe', ['token' => $newToken], UrlGeneratorInterface::ABSOLUTE_URL);
    
    //             $email = (new Email())
    //                 ->from('noreply@ac-guadeloupe.fr')
    //                 ->to($user->getEmail())
    //                 ->subject('Votre session a expiré - Nouveau lien de connexion')
    //                 ->html('<p>Bonjour,</p><p>Votre session a expiré. Cliquez sur le lien suivant pour vous reconnecter : <a href="' . $url . '">' . $url . '</a></p>');
    
    //             $mailer->send($email);
    //         }
    //     }
    
    //     // Supprimer les données de session
    //     $session->clear();
    
    //     // Rediriger vers la page d'accueil ou une autre page
    //     return $this->redirectToRoute('session_expired');
    // }
    



}
