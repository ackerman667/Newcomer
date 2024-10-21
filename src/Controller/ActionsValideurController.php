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
    
      
    $infosPersonne = $demande->getInfosPersonne();
    
   
    if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
     
        $infosPersonne = json_decode($infosPersonne, true);
    }
    
       
       $pdfResponse = $this->generatePdf($id, $entityManager);
    
       
       $pdfOutput = $pdfResponse->getContent();
    
    // Récupérez l'email en fonction du type de demande
    // $email = $demande->isAutrePersonne() ? ($infosPersonne['email'] ?? '') : $demande->getIDutilisateur()->getEmail();
    
    //     $emailMessage = (new Email())
    //         ->from('noreply@ac-guadeloupe.fr')
    //         ->to($email)
    //         ->subject('Votre demande a été envoyée dans LEKA')
    //         ->html('<p>Votre demande a été envoyée dans LEKA.</p>');
    
    //     $mailer->send($emailMessage);
    
    
        $valideurEmail = $this->getValideurMail($demande);
    
        $subject = "La demande numéro $id pour le service {$demande->getService()} a été soumise";
    
    
    
    
    
        // $leka = (new Email())
        //     ->from($valideurEmail)
        //     ->to('nbarbeu@gmail.com')  remplacer par mail LEKA
        //     ->subject($subject) 
        //     ->html('<p>Votre demande a été envoyée dans LEKA.</p>')
        //     ->attach($pdfOutput, 'demande.pdf', 'application/pdf');
    
        // $mailer->send($leka);

    
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
    
        $infosPersonne = $demande->getInfosPersonne();
    

    if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
       
        $infosPersonne = json_decode($infosPersonne, true);
    }
    
    // Récupérez l'email en fonction du type de demande
    $email = $demande->isAutrePersonne() ? ($infosPersonne['email'] ?? '') : $demande->getIDutilisateur()->getEmail();
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
    
   
    $infosPersonne = $demande->getInfosPersonne();
    if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
  
        $infosPersonne = json_decode($infosPersonne, true);
    }
    
    // Récupérez l'email en fonction du type de demande
    $email = $demande->isAutrePersonne() ? $infosPersonne['email'] ?? '' : $demande->getIDutilisateur()->getEmail();
    
    
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
            $infos_personne = $demande->getInfosPersonne();
    
          
            if (!is_array($infos_personne)) {
                $user = json_decode($infos_personne, true);
            } else {
                $user = $infos_personne;
            }
        } else {
         
            $user = $demande->getIDutilisateur();
        }
         $valideur = $demande->getUidValideur();
    
        return $this->render('valideur/visualiser.html.twig', [
            'demande' => $demande,
            'monApplication' => $monApplication,
            'user' => $user,
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
            
            $infosPersonne = $demande->getInfosPersonne();
    
       
            if (is_string($infosPersonne)) {
                $userInfos = json_decode($infosPersonne, true);
            } else {
             
                $userInfos = $infosPersonne;
            }
    
            if (!empty($userInfos['date_de_naissance']) && is_array($userInfos['date_de_naissance'])) {
                $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d H:i:s.u', $userInfos['date_de_naissance']['date']);
            }
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
        
    
        $imagePath = 'C:\Users\nbarbeu\newcomer\public\interfaceappli\css\images\logoaca\academie.png'; 
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageSrc = 'data:image/png;base64,' . $imageData;
    
       
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
    
      
        $html = $this->renderView('visualiser-demandes/pdf.html.twig', [
            'demande' => $demande,
            'user' => $userInfos,
            'ressources' => $ressources,
            'imageSrc' => $imageSrc,
            'valideur' => $valideur
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