<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\Demandes;
use App\Entity\Ressources;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\HistoriqueDemande;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\DemandeEtape1FormType;
use App\Form\DemandeEtape2FormType;
use App\Form\DemandeEtape3FormType;
use App\Form\DemandeFormType;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class ValideurListeController extends AbstractController

{
//     private $timezone;

//     public function __construct()
//     {
//         $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
//     }
   
//     #[Route('formulaireldap/listedemandes', name: 'listedemandes')]
//     public function index(MonApplication $monApplication, EntityManagerInterface $entityManager, Security $security): Response
//     {
//         $user = $security->getUser();
//         $uid = $user->getUid();
//         $statut = 'Brouillons'; 
    
       
//         $demandes = $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
//             ->where('d.uid_valideur = :uid')
//             ->andWhere('d.statuts <> :statut') 
//             ->setParameter('uid', $uid)
//             ->setParameter('statut', $statut)
//             ->getQuery()
//             ->getResult();
    
//         // Préparer les données utilisateur pour chaque demande
//         $userDemandes = [];
//         foreach ($demandes as $demande) {
//             // Si la demande est faite pour une autre personne, utiliser les infos JSON, sinon utiliser les infos de l'utilisateur lié
//             if ($demande->isAutrePersonne()) {
//                 $infos_personne = $demande->getInfosPersonne();
    
//                 // Si $infos_personne est déjà un tableau, l'utiliser directement, sinon le décoder du JSON
//                 if (!is_array($infos_personne)) {
//                     $infos_personne = json_decode($infos_personne, true) ?? []; // Décoder JSON en tableau
//                 }
    
//                 // Préparer les informations de l'utilisateur à partir des données JSON
//                 $userData = [
//                     'nom' => $infos_personne['nom'] ?? '',
//                     'prenom' => $infos_personne['prenom'] ?? '',
//                     'email' => $infos_personne['email'] ?? '',
//                     'date_de_naissance' => $infos_personne['date_de_naissance'] ?? '',
//                     'fonction' => $infos_personne['fonction'] ?? '',
//                     'statut' => $infos_personne['statut'] ?? ''
//                 ];
//             } else {
//                 // Si la demande n'est pas pour une autre personne, utiliser les infos de l'utilisateur associé à la demande
//                 $userEntity = $demande->getIDutilisateur();
//                 $userData = [
//                     'nom' => $userEntity ? $userEntity->getNom() : '',
//                     'prenom' => $userEntity ? $userEntity->getPrenom() : '',
//                     'email' => $userEntity ? $userEntity->getEmail() : '',
//                 ];
//             }
    
//             // Ajouter les informations de la demande et de l'utilisateur à la liste
//             $userDemandes[] = [
//                 'demande' => $demande,
//                 'user' => $userData,
//             ];
//         }
        
//         return $this->render('valideur/index.html.twig', [
//             'demandes' => $demandes,
//             'monApplication' => $monApplication,
//             'user' => $userDemandes,
//         ]);
//     }
    
    


    
//     #[Route('formulaireldap/validerdemande/{id}', name: 'valider_demande')]
// public function validerDemande(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
// {
//     $demande = $entityManager->getRepository(Demandes::class)->find($id);

//     if (!$demande) {
//         throw $this->createNotFoundException('Demande non trouvée.');
//     }

//     $id_demande = $demande->getId();

//     $now = new \DateTime('now', $this->timezone);
//     $demande->setStatuts('Suivi dans LEKA');
//     $demande->setDateValidation($now);

//     // Créer un historique de la demande
//     $historique = new HistoriqueDemande();
//     $historique->setDemande($demande);
//     $historique->setStatut('Suivi dans LEKA');
//     $historique->setStatutOperation('Envoi de la demande dans LEKA');
//     $historique->setDate(new \DateTime());
//     $entityManager->persist($historique);
//     $entityManager->flush();

//     // Récupérer l'email de l'utilisateur ou de la personne cible
//    // Récupérer les informations de la demande
// $infosPersonne = $demande->getInfosPersonne();

// // Vérifiez si les informations sont déjà un tableau ou non
// if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
//     // Décoder le JSON seulement si c'est une chaîne
//     $infosPersonne = json_decode($infosPersonne, true);
// }

//    // Générer le PDF
//    $pdfResponse = $this->generatePdf($id, $entityManager);

//    // Récupérer le contenu du PDF généré
//    $pdfOutput = $pdfResponse->getContent();

// // Récupérez l'email en fonction du type de demande
// $email = $demande->isAutrePersonne() ? ($infosPersonne['email'] ?? '') : $demande->getIDutilisateur()->getEmail();

//     $emailMessage = (new Email())
//         ->from('noreply@ac-guadeloupe.fr')
//         ->to($email)
//         ->subject('Votre demande a été envoyée dans LEKA')
//         ->html('<p>Votre demande a été envoyée dans LEKA.</p>');

//     $mailer->send($emailMessage);


//     $valideurEmail = $this->getValideurMail($demande);

//     $subject = "La demande numéro $id pour le service {$demande->getService()} a été soumise";





//     // $leka = (new Email())
//     //     ->from($valideurEmail)
//     //     ->to('nbarbeu@gmail.com')  remplacer par mail LEKA
//     //     ->subject($subject) 
//     //     ->html('<p>Votre demande a été envoyée dans LEKA.</p>')
//     //     ->attach($pdfOutput, 'demande.pdf', 'application/pdf');

//     // $mailer->send($leka);





//     return $this->redirectToRoute('listedemandes');
// }


// #[Route('formulaireldap/refuserdemande/{id}', name: 'refuser_demande')]
// public function refuserDemande(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
// {
//     $demande = $entityManager->getRepository(Demandes::class)->find($id);

//     if (!$demande) {
//         throw $this->createNotFoundException('Demande non trouvée.');
//     }

//     $demande->setStatuts('Refusée');
//     $historique = new HistoriqueDemande();
//     $historique->setDemande($demande);
//     $historique->setStatut('Refusée');
//     $historique->setStatutOperation('Refus');
//     $historique->setDate(new \DateTime());
//     $entityManager->persist($historique);
//     $entityManager->flush();

//     $infosPersonne = $demande->getInfosPersonne();

// // Vérifiez si les informations sont déjà un tableau ou non
// if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
//     // Décoder le JSON seulement si c'est une chaîne
//     $infosPersonne = json_decode($infosPersonne, true);
// }

// // Récupérez l'email en fonction du type de demande
// $email = $demande->isAutrePersonne() ? ($infosPersonne['email'] ?? '') : $demande->getIDutilisateur()->getEmail();
//     $emailMessage = (new Email())
//         ->from('noreply@ac-guadeloupe.fr')
//         ->to($email)
//         ->subject('Votre demande a été refusée')
//         ->html('<p>Votre demande a été refusée.</p>');

//     $mailer->send($emailMessage);

//     return $this->redirectToRoute('listedemandes');
// }


// #[Route('formulaireldap/commenterdemande/{id}', name: 'commenter_demande', methods: ['POST'])]
// public function commenterDemande(int $id, Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
// {
//     $demande = $entityManager->getRepository(Demandes::class)->find($id);

//     if (!$demande) {
//         throw $this->createNotFoundException('Demande non trouvée.');
//     }

//     $commentaire = $request->request->get('commentaire');
//     $demande->setCommentaire($commentaire);
    
//     $historique = new HistoriqueDemande();
//     $historique->setDemande($demande);
//     $historique->setStatut($demande->getStatuts());
//     $historique->setStatutOperation('Commentaire');
//     $historique->setDate(new \DateTime());
//     $entityManager->persist($historique);
//     $entityManager->flush();

//    // Vérifiez si infos_personne est déjà un tableau ou non
// $infosPersonne = $demande->getInfosPersonne();
// if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
//     // Décoder le JSON seulement si c'est une chaîne
//     $infosPersonne = json_decode($infosPersonne, true);
// }

// // Récupérez l'email en fonction du type de demande
// $email = $demande->isAutrePersonne() ? $infosPersonne['email'] ?? '' : $demande->getIDutilisateur()->getEmail();


//     $emailMessage = (new Email())
//         ->from('noreply@ac-guadeloupe.fr')
//         ->to($email)
//         ->subject('Votre demande a reçu un commentaire')
//         ->html('<p>Votre demande a reçu un commentaire : ' . $commentaire . '</p>');

//     $mailer->send($emailMessage);

//     return $this->redirectToRoute('listedemandes');
// }


// #[Route('formulaireldap/demande/visualiser/{id}', name: 'visualiser_demande')]
// public function visualiserDemande(MonApplication $monApplication, int $id, EntityManagerInterface $entityManager): Response
// {
//     $demande = $entityManager->getRepository(Demandes::class)->find($id);

//     if (!$demande) {
//         throw $this->createNotFoundException('Demande non trouvée.');
//     }

//     $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);

//     // Vérifier si la demande est pour une autre personne
//     if ($demande->isAutrePersonne()) {
//         $infos_personne = $demande->getInfosPersonne();

//         // Si infos_personne n'est pas déjà un tableau, décoder JSON
//         if (!is_array($infos_personne)) {
//             $user = json_decode($infos_personne, true);
//         } else {
//             $user = $infos_personne;
//         }
//     } else {
//         // Sinon, récupérer les informations de l'utilisateur associé à la demande
//         $user = $demande->getIDutilisateur();
//     }
//      $valideur = $demande->getUidValideur();

//     return $this->render('valideur/visualiser.html.twig', [
//         'demande' => $demande,
//         'monApplication' => $monApplication,
//         'user' => $user,
//         'ressources' => $ressources,
//         'valideur' => $valideur
//     ]);
// }






// #[Route('formulaireldap/demandepdf/{id}', name: 'demande_pdf_valideur')]
// public function generatePdf($id, EntityManagerInterface $entityManager): Response
// {
//     $demande = $entityManager->getRepository(Demandes::class)->find($id);

//     // Vérifier si la demande est valide
//     if (!$demande) {
//         throw $this->createNotFoundException('Demande non trouvée.');
//     }

//     $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);

//     // Vérifier si la demande est faite pour une autre personne
//     if ($demande->isAutrePersonne()) {
//         // Récupérer les informations à partir du JSON
//         $infosPersonne = $demande->getInfosPersonne();

//         // Vérifier que le contenu est une chaîne JSON avant d'appeler json_decode
//         if (is_string($infosPersonne)) {
//             $userInfos = json_decode($infosPersonne, true);
//         } else {
//             // Si ce n'est pas une chaîne, on considère que c'est déjà un tableau
//             $userInfos = $infosPersonne;
//         }

//         if (!empty($userInfos['date_de_naissance']) && is_array($userInfos['date_de_naissance'])) {
//             $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d H:i:s.u', $userInfos['date_de_naissance']['date']);
//         }
//     } else {
//         // Récupérer les informations de l'utilisateur lié
//         $userInfos = [
//             'nom' => $demande->getIDutilisateur()->getNom(),
//             'prenom' => $demande->getIDutilisateur()->getPrenom(),
//             'email' => $demande->getIDutilisateur()->getEmail(),
//             'date_de_naissance' => $demande->getIDutilisateur()->getDateDeNaissance(),
//             'fonction' => $demande->getIDutilisateur()->getFonction(),
//             'statut' => $demande->getIDutilisateur()->getStatutPersonne(),
//             'date_debut' => $demande->getIDutilisateur()->getDateDebut(),
//             'date_fin' => $demande->getIDutilisateur()->getDateFin(),
//         ];
//     }


//     $valideur = $demande->getUidValideur();
    

//     $imagePath = 'C:\Users\nbarbeu\newcomer\public\interfaceappli\css\images\logoaca\academie.png'; 
//     $imageData = base64_encode(file_get_contents($imagePath));
//     $imageSrc = 'data:image/png;base64,' . $imageData;

//     // Configurer Dompdf selon vos besoins
//     $options = new Options();
//     $options->set('defaultFont', 'Arial');
//     $dompdf = new Dompdf($options);

//     // Récupérer le contenu HTML de votre template
//     $html = $this->renderView('consult/pdf.html.twig', [
//         'demande' => $demande,
//         'user' => $userInfos,
//         'ressources' => $ressources,
//         'imageSrc' => $imageSrc,
//         'valideur' => $valideur
//     ]);

//     // Charger le HTML dans Dompdf
//     $dompdf->loadHtml($html);
//     $dompdf->setPaper('A4', 'portrait');
//     $dompdf->render();

//     // Envoyer le PDF au navigateur
//     return new Response($dompdf->output(), 200, [
//         'Content-Type' => 'application/pdf',
//         'Content-Disposition' => 'inline; filename="demande.pdf"',
//     ]);
// }

// public function getValideurMail(Demandes $demande): string
// {
//     $uidValideur = $demande->getUidValideur(); // Récupère l'UID du valideur
//     if ($uidValideur) {
//         // Génère l'adresse e-mail en ajoutant le domaine
//         return $uidValideur . '@ac-guadeloupe.fr';
//     }

//     // Valeur par défaut si l'UID est manquant
//     return 'noreply@ac-guadeloupe.fr';
// }






}
  

