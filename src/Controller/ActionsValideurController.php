<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use App\Service\UserRoleChecker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Ressources;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Security\UserInformation;
use App\Entity\HistoriqueDemande;
use Symfony\Bundle\SecurityBundle\Security;;


class ActionsValideurController extends AbstractController
{
    private $security;
    private $roleChecker;
    private $timezone;

    public function __construct(Security $security, UserRoleChecker $roleChecker)
    {
        $this->security = $security;
        $this->roleChecker = $roleChecker;
        $this->timezone = new \DateTimeZone('America/Guadeloupe');
        $this->isValideur = $this->roleChecker->isUserValideur();
    }



    #[Route('formulaireldap/validerdemande/{id}', name: 'valider_demande')]
    public function validerDemande(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessValideur();
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $id_demande = $demande->getId();
    
        $now = new \DateTime('now', $this->timezone);
        $demande->setStatuts('Suivi dans LEKA');
        $demande->setDateValidation($now);
    
       
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut('Suivi dans LEKA');
        $historique->setStatutOperation('Envoi de la demande dans LEKA');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);
        $entityManager->flush();
    
       
       $pdfResponse = $this->generatePdf($id, $entityManager);
       $pdfOutput = $pdfResponse->getContent();
    
    // Récupérez l'email en fonction du type de demande
    $email = $demande->isAutrePersonne() ? $demande->getAutreUtilisateur()->getEmail() : $demande->getIDutilisateur()->getEmail();
    
        $emailMessage = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($email)
            ->subject('Votre demande a été envoyée dans LEKA')
            ->html('<p>Votre demande a été envoyée dans LEKA.</p>');
    
        $mailer->send($emailMessage);
    
    
        $valideurEmail = $this->getValideurMail($demande);
    
        $subject = "La demande numéro $id pour le service {$demande->getService()} a été validée";
    
    
    
    
    
        $leka = (new Email())
            ->from($valideurEmail)
            ->to('lekadempp@ac-guadeloupe.fr') 
            ->subject($subject) 
            ->html('<p>Votre demande a été envoyée dans LEKA.</p>')
            ->attach($pdfOutput, 'demande.pdf', 'application/pdf');
    
        $mailer->send($leka);

    
        return $this->redirectToRoute('liste_demandes');
    }
    
    
    #[Route('formulaireldap/refuserdemande/{id}', name: 'refuser_demande')]
    public function refuserDemande(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessValideur();
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $demande->setStatuts('Refusée');
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut('Refusée');
        $historique->setStatutOperation('Refus');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);
        $entityManager->flush();
    
        
    // Récupérez l'email en fonction du type de demande
    $email = $demande->isAutrePersonne() ? $demande->getAutreUtilisateur()->getEmail() : $demande->getIDutilisateur()->getEmail();
        $emailMessage = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($email)
            ->subject('Votre demande a été refusée')
            ->html('<p>Votre demande a été refusée.</p>');
    
        $mailer->send($emailMessage);
    
        return $this->redirectToRoute('liste_demandes');
    }
    
    
    #[Route('formulaireldap/commenterdemande/{id}', name: 'commenter_demande', methods: ['POST'])]
    public function commenterDemande(int $id, Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessValideur();
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $commentaire = $request->request->get('commentaire');
        $demande->setCommentaire($commentaire);
        
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut($demande->getStatuts());
        $historique->setStatutOperation('Commentaire');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);
        $entityManager->flush();
    
   
    
    
    // Récupérez l'email en fonction du type de demande
    $email = $demande->isAutrePersonne() ? $demande->getAutreUtilisateur()->getEmail() : $demande->getIDutilisateur()->getEmail();
    
    
        // $emailMessage = (new Email())
        //     ->from('noreply@ac-guadeloupe.fr')
        //     ->to($email)
        //     ->subject('Votre demande a reçu un commentaire')
        //     ->html('<p>Votre demande a reçu un commentaire : ' . $commentaire . '</p>');
    
        // $mailer->send($emailMessage);
    
        return $this->redirectToRoute('liste_demandes');
    }
    
    
    #[Route('formulaireldap/demande/visualiser/{id}', name: 'visualiser_demande')]
    public function visualiserDemande(MonApplication $monApplication, int $id, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
    
        if ($demande->isAutrePersonne()) {
            $user = $demande->getAutreUtilisateur();
            
        } else {
            // Si la demande n'est pas pour une autre personne, utiliser l'utilisateur lié à la demande
            $user = $demande->getIDutilisateur();
        }
        $valideur = $demande->getUidValideur();
    
        return $this->render('visualiser-demandes/visualiser.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'monApplication' => $monApplication,
            'ressources' => $ressources,
            'valideur' => $valideur
        ]);
    }

    #[Route('formulaireldap/demandepdf/{id}', name: 'demande_pdf_valideur')]
    public function generatePdf($id, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
    
        
        if ($demande->isAutrePersonne()) {
            $user = $demande->getAutreUtilisateur(); 
            $userInfos = [
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getEmail(),
            'date_de_naissance' => $user->getDateDeNaissance(),
            'fonction' => $user->getFonction(),
            'statut' => $user->getStatutPersonne(),
            'date_debut' => $user->getDateDebut(),
            'date_fin' => $user->getDateFin(),
        ];
            
            // $infosPersonne = $demande->getInfosPersonne();
    
       
            // if (is_string($infosPersonne)) {
            //     $userInfos = json_decode($infosPersonne, true);
            // } else {
             
            //     $userInfos = $infosPersonne;
            // }
    
            // if (!empty($userInfos['date_de_naissance']) && is_array($userInfos['date_de_naissance'])) {
            //     $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d H:i:s.u', $userInfos['date_de_naissance']['date']);
            // }
        } else {
            
            $userInfos = [
                'nom' => $demande->getIDutilisateur()->getNom(),
                'prenom' => $demande->getIDutilisateur()->getPrenom(),
                'email' => $demande->getIDutilisateur()->getEmail(),
                'date_de_naissance' => $demande->getIDutilisateur()->getDateDeNaissance(),
                'fonction' => $demande->getIDutilisateur()->getFonction(),
                'statut' => $demande->getIDutilisateur()->getStatutPersonne(),
                'date_debut' => $demande->getIDutilisateur()->getDateDebut(),
                'date_fin' => $demande->getIDutilisateur()->getDateFin(),
            ];
        }
    
    
        $valideur = $demande->getUidValideur();
        
    
      $imagePath = $this->getParameter('kernel.project_dir') . '/public/interfaceappli/css/images/10_logoAC_GUADELOUPE_web.png';
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageSrc = 'data:image/png;base64,' . $imageData;
    
       
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
    
      
        $html = $this->renderView('valideur/pdf_valideur.html.twig', [
            'demande' => $demande,
            'user' => $userInfos,
            'ressources' => $ressources,
            'imageSrc' => $imageSrc,
            'valideur' => $valideur,
            // 'autreUtilisateur'  => $autreUtilisateur,
        ]);
    
     
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
    
        
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="demande.pdf"',
        ]);
    }



    public function getValideurMail(Demandes $demande): string
    {
        $uidValideur = $demande->getUidValideur(); 
        if ($uidValideur) {
           
            return $uidValideur . '@ac-guadeloupe.fr';
        }
    
        // Valeur par défaut si l'UID est manquant
        return 'noreply@ac-guadeloupe.fr';
    }
    
    


    private function denyAccessUnlessValideur()
    {
        if (!$this->isValideur) {
            throw $this->createAccessDeniedException('Vous devez être un valideur pour accéder à cette section.');
        }
    }

}