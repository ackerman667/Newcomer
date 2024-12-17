<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use App\Entity\HistoriqueDemande;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\Ressources;
use Symfony\Component\HttpFoundation\Cookie; // Ajout du namespace correct

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
    #[Route('/statuts/{token}', name: 'demande_externe')]
    public function index( SessionInterface $session, MonApplication $monApplication, EntityManagerInterface $entityManager, $token): Response
    {
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
     
        if (!$session->has('externe_auth')) {
            $session->set('externe_auth', true);
        }

        if (!$session->has('externe_token')) {
            $session->set('externe_token', $token);
        }
        
        // $session = $this->requestStack->getSession();
        // $session->set('externe_auth', true);
        // $session->set('externe_token', $token);

       
        $sessionData = $session->all();

        
        dump($sessionData);
        




        $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);
        $demandes = $entityManager->getRepository(Demandes::class)->findBy(['IDutilisateur' => $user]);

        dump($demandes);
        

        return $this->render('demandes/demandes_externe.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
            'user' => $user,
        ]);
    }

    #[Route('/demande/consult/{token}', name: 'demande_consult')]
    public function consult(MonApplication $monApplication, $token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
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
  

    #[Route('/demande/pdf/{token}', name: 'demande_pdf')]
    public function generatePdf(/*Demandes $demande , */MonApplication $monApplication, $token, EntityManagerInterface $entityManager): Response
    {
        
        // $token = $demande->getToken();
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
    
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
    
    


    #[Route('/formulaireexterne/nouvelle_demande/{token}', name: 'nouvelle_demande')]
    public function nouvelleDemande(SessionInterface $session, $token): Response
    {
       
        $session->remove('form_data');
        $session->remove('demande_id');
        $session->set('nouvelle_demande', true);
    
        
        return $this->redirectToRoute('formulaireexterne_etape1', ['token' => $token]);
    }
    

    #[Route('/formulaireexterne/supprimer/{id}', name: 'formulaireexterne_supprimer')]
    public function supprimerDemande(Request $request, EntityManagerInterface $entityManager, $id): Response
    {
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

        return $this->redirectToRoute('demande_externe' , ['token' => $token]); 
    }

    #[Route('/formulaireexterne/modifier/{id}', name: 'modifier_demandes')]
    public function modifierDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id, MailerInterface $mailer): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $user = $demande->getIDutilisateur();
        $token = $demande->getToken();

        $data = [
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

        $session->set('form_data', $data);
        $session->set('demande_id', $id);

        return $this->redirectToRoute('formulaireexterne_etape1', ['token' => $token]);
    }

    #[Route('/formulaireexterne/valider/{id}', name: 'valider_demandes')]
    public function validerDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id,MailerInterface $mailer): Response
    {
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
    
       

        return $this->redirectToRoute('demande_externe', ['token' => $token]);
    }


    #[Route('/logout', name: 'app_logout')]
    public function logout(): Response
    {
       
        $session = $this->requestStack->getSession();
        $session->remove('externe_auth');
        $session->clear();

        
        return $this->redirectToRoute('home');
    }




}
