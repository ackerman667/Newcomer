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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Dompdf\Options;

class StatutsExterneController extends AbstractController
{

    private $timezone;

    public function __construct()
    {
        
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
    }
    #[Route('/statuts/{token}', name: 'demande_externe')]
    public function index(SessionInterface $session, MonApplication $monApplication, EntityManagerInterface $entityManager, $token): Response
    {
        $session->clear();
        $sessionData = $session->all();

        // Utilisez dump() pour afficher le contenu de la session (nécessite le composant de débogage activé)
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


        


        // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
        //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        // }

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
    // #[Route('/demande/consult/{id}', name: 'demande_consult')]
    // public function consult(MonApplication $monApplication, int $id, EntityManagerInterface $entityManager): Response
    // {
    //     $demande = $entityManager->getRepository(Demandes::class)->find($id);
    //     $valideur = $demande->getUidValideur();


        


    //     // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
    //     //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
    //     // }

    //     $user = $demande->getIDutilisateur();
    //     $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);

    //     return $this->render('consult/visualiser.html.twig', [
    //         'demande' => $demande,
    //         'user' => $user,
    //         'monApplication' => $monApplication,
    //         'ressources' => $ressources,
    //         'valideur' => $valideur
    //     ]);
    // }

    #[Route('/demande/pdf/{token}', name: 'demande_pdf')]
public function generatePdf(Demandes $demande, MonApplication $monApplication,/* $token,*/ EntityManagerInterface $entityManager): Response
{
    // $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
    $token = $demande->getToken();


    $user = $demande->getIDutilisateur();
    $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);

    $valideur = $demande->getUidValideur();

    // Encoder l'image en base64
    $imagePath = 'C:\Users\nbarbeu\newcomer\public\interfaceappli\css\images\logoaca\academie.png';
    $imageData = base64_encode(file_get_contents($imagePath));
    $imageSrc = 'data:image/png;base64,' . $imageData;

    // Configurer Dompdf
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);

    // Récupérer le contenu HTML de votre template
    $html = $this->renderView('visualiser-demandes/index.html.twig', [
        'demande' => $demande,
        'user' => $user,
        'ressources' => $ressources,
        'monApplication' => $monApplication,
        'imageSrc' => $imageSrc,
        'valideur' => $valideur, // Passer l'image encodée à la vue
    ]);

    // Charger le HTML dans Dompdf
    $dompdf->loadHtml($html);

    // Définir le format du papier
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
        // Réinitialiser les données de la session pour démarrer une nouvelle demande
        $session->remove('form_data');
        $session->remove('demande_id');
        $session->set('nouvelle_demande', true); // Indiquer explicitement qu'une nouvelle demande doit être créée
    
        // Rediriger vers la première étape du formulaire pour une nouvelle demande
        return $this->redirectToRoute('formulaireexterne_etape1', ['token' => $token]);
    }
    

    #[Route('/formulaireexterne/supprimer/{id}', name: 'formulaireexterne_supprimer')]
    public function supprimerDemande(Request $request, EntityManagerInterface $entityManager, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        //   $x = $demande.getIDUtilisateur();
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
        $demande->setStatuts('En attente');
        $entityManager->persist($demande);
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
                $historique->setStatut('Envoyée');
                
                $historique->setDate(new \DateTime('now', $this->timezone));
                $historique->setStatutOperation('Envoie de la demande');

                $entityManager->persist($historique);
        $entityManager->flush();

        $pdfResponse = $this->generatePdf($demande, $monApplication, $entityManager);


        // Récupérer le contenu du PDF généré
        $pdfOutput = $pdfResponse->getContent();
    
        // Créer l'email
        $email = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($user->getEmail())
            ->subject('Vous avez envoyé la demande.')
            ->text('Vous avez envoyé la demande.')
            ->html('<p>Bonjour, vous trouverez ci-joint votre demande en PDF.</p>');
    
        // Ajouter le PDF en pièce jointe
        $email->attach($pdfOutput, 'demande.pdf', 'application/pdf');
    
            $mailer->send($email);
    
       

        return $this->redirectToRoute('demande_externe', ['token' => $token]);
    }



}
