<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use App\Service\UserRoleChecker;
use App\Service\SuperUserChecker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Ressources;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\TemporaryData;
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
    private $superUserChecker;

    public function __construct(Security $security, UserRoleChecker $roleChecker, SuperUserChecker $superUserChecker)
    {
        $this->security = $security;
        $this->roleChecker = $roleChecker;
        $this->timezone = new \DateTimeZone('America/Guadeloupe');
        $this->superUserChecker = $superUserChecker;
        $this->isSuperUser = $this->superUserChecker->isSuperUser();
        $this->isValideur = $this->roleChecker->isUserValideur();
    }


/**
 * @brief Prépare la modification d'une demande pour un valideur.
 *
 * Cette méthode initialise crée une entrée dans la table temporaire pour un valideur afin de modifier une demande.
 * Les données de la table sont valables pendant 24 heures .
 *
 * @Route('/formulaireldap/modifierdemandes/{id}', name='preparer_modification_valideur')
 *
 * @param int $id Identifiant de la demande à modifier.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return Response Redirige vers la première étape de modification.
 *
 * @details
 * - Vérifie les permissions de l'utilisateur et le statut de la demande.
 * - Crée une entrée dans la table  temporaire pour stocker les informations liées à la demande.
 *
 * @throws AccessDeniedException Si l'utilisateur n'est pas un valideur ou n'a pas les droits.
 * @throws NotFoundHttpException Si la demande n'existe pas.
 */

    #[Route('/formulaireldap/modifierdemandes/{id}', name: 'preparer_modification_valideur')]
public function preparerModificationValideur(int $id, EntityManagerInterface $entityManager): Response
{
    $this->checkUserPermissionForDemande($id, $entityManager);
    $this->checkStatuts($id, $entityManager);
    $this->denyAccessUnlessValideur();
   

    $demande = $entityManager->getRepository(Demandes::class)->find($id);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

   
    $userBdd = $this->findOrCreateLdapUser($entityManager);
    $temporaryData = new TemporaryData();
    $temporaryData->setUser($userBdd);
    $temporaryData->setAction('modifier'); 
    $temporaryData->setData([]); 
    $temporaryData->setExpiration((new \DateTime())->modify('+24 hours'));

    $entityManager->persist($temporaryData);
    $entityManager->flush();

    // Rediriger vers l'étape 1
    return $this->redirectToRoute('modifier_demandesvalideur_etape1', [
        'id' => $id,
        'token' => $temporaryData->getToken(),
    ]);
}



/**
 * @brief Valide une demande et la marque comme "Suivi dans LEKA".
 *
 * Cette méthode met à jour le statut d'une demande et enregistre l'action dans l'historique.
 * Elle génère également un PDF et prépare l'envoi d'une notification par e-mail.
 *
 * @Route('formulaireldap/validerdemande/{id}', name='valider_demande')
 *
 * @param int $id Identifiant de la demande à valider.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param MailerInterface $mailer Service d'envoi d'e-mails.
 *
 * @return Response Redirige vers la liste des demandes à valider.
 *
 * @details
 * - Change le statut de la demande à "Suivi dans LEKA".
 * - Enregistre l'historique de l'opération avec des métadonnées.
 * - Prépare un PDF associé à la demande.
 *
 * @throws AccessDeniedException Si l'utilisateur n'est pas un valideur ou n'a pas les droits.
 * @throws NotFoundHttpException Si la demande n'existe pas.
 */

    #[Route('formulaireldap/validerdemande/{id}', name: 'valider_demande')]
    public function validerDemande(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessValideur();
        $this->checkStatuts($id, $entityManager);
        $this->checkUserPermissionForDemande($id, $entityManager);
        $user = $this->security->getUser();
        $uid = $user->getUid();
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

           
        $mailValideur = $uid . '@ac-guadeloupe.fr';
     
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
    
        // $emailMessage = (new Email())
        //     ->from('noreply@ac-guadeloupe.fr')
        //     ->to($email)
        //     ->subject('Votre demande a été envoyée dans LEKA')
        //     ->html('<p>Votre demande a été envoyée dans LEKA.</p>');
    
        // $mailer->send($emailMessage);
    
    
       
    
        // $subject = "La demande numéro $id pour le service {$demande->getService()} a été validée";
    
        $subject = "Demande d'accès à un poste informatique : La  demande numéro $id pour le service {$demande->getService()} a été validée";
        // $valideurEmail = $this->getValideurMail($demande);
        $testeurMail = (new Email())
            ->from($mailValideur)
            ->to('nbarbeu@gmail.com')
            ->subject($subject)
            ->html("<p> Veuillez trouver en pièce jointe le fichier PDF contenant les détails de la demande $id: </p>")
            ->attach($pdfOutput, 'demande.pdf', 'application/pdf');
    
        $mailer->send($testeurMail);
    
    
      
    
      
    
    
    
    
    
        $leka = (new Email())
            ->from($mailValideur)
            ->to('lekadem@ac-guadeloupe.fr') 
            ->subject($subject) 
            ->html('<p>Votre demande a été envoyée dans LEKA.</p>')
            ->attach($pdfOutput, 'demande.pdf', 'application/pdf');
    
        $mailer->send($leka);

    
        if ($this->superUserChecker->isSuperUser()) {
                return $this->redirectToRoute('admin_demandes'); 
            } else {
                return $this->redirectToRoute('demandes_a_valider'); 
            }
    }






    #[Route('formulaireldap/valider-demande/{id}', name: 'valider_monservice')]
    public function validerDemandeOwnService(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        
        $this->denyAccessUnlessValideur();
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        

    if ($demande->getStatuts() !== 'Brouillons') {
        throw $this->createAccessDeniedException('Vous ne pouvez pas agir sur cette demande car elle est deja validée".');
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
    
        // $emailMessage = (new Email())
        //     ->from('noreply@ac-guadeloupe.fr')
        //     ->to($email)
        //     ->subject('Votre demande a été envoyée dans LEKA')
        //     ->html('<p>Votre demande a été envoyée dans LEKA.</p>');
    
        // $mailer->send($emailMessage);
    
    
        // $valideurEmail = $this->getValideurMail($demande);
    
        // $subject = "La demande numéro $id pour le service {$demande->getService()} a été validée";
    
    
        // $subject = "La demande numéro $id pour le service {$demande->getService()} a été validée";
        // $valideurEmail = $this->getValideurMail($demande);
        // $testeurMail = (new Email())
        //     ->from('noreply@ac-guadeloupe.fr')
        //     ->to('nbarbeu@gmail.com')
        //     ->subject($subject)
        //     ->html('<p>Email de test envoie de leka // PDF  : </p>'. $valideurEmail)
        //     ->attach($pdfOutput, 'demande.pdf', 'application/pdf');
    
        // $mailer->send($testeurMail);
    
    
        // $leka = (new Email())
        //     ->from($valideurEmail)
        //     ->to('lekadempp@ac-guadeloupe.fr') 
        //     ->subject($subject) 
        //     ->html('<p>Votre demande a été envoyée dans LEKA.</p>')
        //     ->attach($pdfOutput, 'demande.pdf', 'application/pdf');
    
        // $mailer->send($leka);

    
        return $this->redirectToRoute('mes_demandes');
    }


    


    /**
 * @brief Refuse une demande et enregistre l'action dans l'historique.
 *
 * Cette méthode permet de refuser une demande  en changeant le statut de la demande en le mettant à Refusée et de notifier l'utilisateur concerné.
 *
 * @Route('formulaireldap/refuserdemande/{id}', name='refuser_demande')
 *
 * @param int $id Identifiant de la demande à refuser.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param MailerInterface $mailer Service d'envoi d'e-mails.
 *
 * @return Response Redirige vers la liste des demandes à valider.
 *
 * @details
 * - Met à jour le statut de la demande à "Refusée".
 * - Enregistre l'opération dans l'historique.
 */

    
    #[Route('formulaireldap/refuserdemande/{id}', name: 'refuser_demande')]
    public function refuserDemande(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        
        $this->denyAccessUnlessValideur();
        $this->checkStatuts($id, $entityManager);
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        // if(empty($demande.getCommentaire())) {
        //     $this->addFlash('error', 'Un commentaire est requis pour refuser la demande.');
        //     return $this->redirectToRoute('demandes_a_valider');

        // }


       
    
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
        // $emailMessage = (new Email())
        //     ->from('noreply@ac-guadeloupe.fr')
        //     ->to($email)
        //     ->subject('Votre demande a été refusée')
        //     ->html('<p>Votre demande a été refusée.</p>');
    
        // $mailer->send($emailMessage);
    
        if ($this->superUserChecker->isSuperUser()) {
                return $this->redirectToRoute('admin_demandes'); 
            } else {
                return $this->redirectToRoute('demandes_a_valider'); 
            }
    }
    


    /**
 * @brief Ajoute un commentaire à une demande.
 *
 * Cette méthode permet à un valideur de commenter une demande et d'en informer l'utilisateur concerné.
 *
 * @Route('formulaireldap/commenterdemande/{id}', name='commenter_demande', methods=['POST'])
 *
 * @param int $id Identifiant de la demande.
 * @param Request $request La requête HTTP contenant le commentaire.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param MailerInterface $mailer Service d'envoi d'e-mails.
 *
 * @return Response Redirige vers la liste des demandes à valider.
 *
 * @details
 * - Ajoute un commentaire à la demande et met à jour l'historique.
 * - Prépare un e-mail de notification contenant le commentaire.
 */

    
    #[Route('formulaireldap/commenterdemande/{id}', name: 'commenter_demande', methods: ['POST'])]
    public function commenterDemande(int $id, Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessValideur();
        $this->checkStatuts($id, $entityManager);
        $this->checkUserPermissionForDemande($id, $entityManager);
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
    
        if ($this->superUserChecker->isSuperUser()) {
                return $this->redirectToRoute('admin_demandes'); 
            } else {
                return $this->redirectToRoute('demandes_a_valider'); 
            }
    }
    
    
    /**
 * @brief Affiche les détails d'une demande.
 *
 * Cette méthode permet de visualiser toutes les informations relatives à une demande donnée,
 * y compris les ressources associées et les informations utilisateur.
 *
 * @Route('formulaireldap/demande/visualiser/{id}', name='visualiser_demande')
 *
 * @param MonApplication $monApplication Informations sur l'application.
 * @param int $id Identifiant de la demande.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return Response La page contenant les détails de la demande.
 *
 * @throws AccessDeniedException Si l'utilisateur n'a pas les droits d'accès à cette demande.
 * @throws NotFoundHttpException Si la demande est introuvable.
 */

    #[Route('formulaireldap/demande/visualiser/{id}', name: 'visualiser_demande')]
    public function visualiserDemande(MonApplication $monApplication, int $id, EntityManagerInterface $entityManager): Response
    {
        
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $this->checkUserPermissionForDemande($id, $entityManager);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
        $ressourcesDecoded = json_decode($ressources->getContenu(), true);
    
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
            'ressourcesList' => $ressourcesDecoded,
            'ressources' => $ressources,
            'valideur' => $valideur
        ]);
    }


    /**
 * @brief Génère un PDF contenant les informations d'une demande.
 *
 * Cette méthode compile les informations d'une demande et les rend dans un format PDF.
 *
 * @Route('formulaireldap/demandepdf/{id}', name='demande_pdf_valideur')
 *
 * @param int $id Identifiant de la demande.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return Response Le fichier PDF généré.
 *
 * @throws AccessDeniedException Si l'utilisateur n'a pas les droits d'accès à cette demande.
 * @throws NotFoundHttpException Si la demande est introuvable.
 */

    #[Route('formulaireldap/demandepdf/{id}', name: 'demande_pdf_valideur')]
    public function generatePdf($id, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $this->checkUserPermissionForDemande($id, $entityManager);
    
    
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


/**
 * @brief Récupère l'adresse e-mail d'un valideur associé à une demande.
 *
 * @param Demandes $demande La demande pour laquelle récupérer l'e-mail.
 *
 * @return string L'adresse e-mail du valideur.
 *
 * @details
 * - Si l'UID du valideur est présent, l'adresse e-mail est construite dynamiquement.
 * - Retourne une adresse par défaut si aucune information n'est disponible.
 */

    public function getValideurMail(Demandes $demande): string
    {
        $uidValideur = $demande->getUidValideur(); 
        if ($uidValideur) {
           
            return $uidValideur . '@ac-guadeloupe.fr';
        }
    
        // Valeur par défaut si l'UID est manquant
        return 'noreply@ac-guadeloupe.fr';
    }
    
    

    /**
 * @brief Refuse l'accès aux utilisateurs qui ne sont pas valideurs.
 *
 * Cette méthode vérifie si l'utilisateur connecté dispose des droits de valideur.
 * Si ce n'est pas le cas, elle lève une exception pour refuser l'accès à la ressource.
 *
 * @details
 * - Utilise la propriété `$this->isValideur`, définie dans le constructeur, pour déterminer
 *   si l'utilisateur a le rôle de valideur.
 * - Si l'utilisateur n'est pas un valideur, une exception d'accès refusé est levée.
 *
 * @throws AccessDeniedException Si l'utilisateur n'est pas un valideur.
 *

 */



    private function denyAccessUnlessValideur()
    {
        $isSuperUser  =  $this->superUserChecker->isSuperUser();
        if ($isSuperUser) {
            // Si l'utilisateur est un super utilisateur, on bypass la vérification.
            return;
        }
        if (!$this->isValideur) {
            throw $this->createAccessDeniedException('Vous devez être un valideur pour accéder à cette section.');
        }
    }



    /**
 * @brief Recherche ou crée un utilisateur LDAP dans la base de données.
 *
 * Cette méthode permet de vérifier si l'utilisateur actuellement connecté existe
 * dans la base de données. Si ce n'est pas le cas, elle crée un nouvel utilisateur
 * avec les informations fournies par LDAP.
 *
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return User L'utilisateur trouvé ou nouvellement créé.
 *
 * @details
 * - Si aucun utilisateur n'est connecté, une exception logique est levée.
 * - Si l'utilisateur connecté n'existe pas en base, un nouvel enregistrement est créé , on cree l'utilisateur LDAP dans la base de données.
 * - Les informations LDAP utilisées incluent : nom, prénom, email, date de naissance, etc.
 * - Les données sont persistées et sauvegardées dans la base de données.
 *
 * @throws LogicException Si aucun utilisateur n'est connecté.
 *
 
 */



    public function findOrCreateLdapUser(EntityManagerInterface $entityManager): User
    {
        // Récupérer l'utilisateur actuellement connecté
        $currentUser = $this->security->getUser();

        if (!$currentUser) {
            throw new \LogicException('Aucun utilisateur connecté.');
        }

        $uid = $currentUser->getUid();

        // Recherche de l'utilisateur en base
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'uid' => $uid,
            'provenance' => 'ldap',
        ]);

        // Si l'utilisateur n'existe pas, le créer
        if (!$user) {
            $userInformation = new UserInformation();
            $infos_user = $userInformation->getUserInformation($currentUser);

            $nom_utilisateur = $infos_user['sn'];
            $prenom_utilisateur = $infos_user['givenname'];
            $email_utilisateur = $infos_user['mail'];
            $dateString = $infos_user['datenaissance'];
            $date = \DateTimeImmutable::createFromFormat('d/m/Y', $dateString);
            $datedenaissance_utilisateur = $date;
            $user = new User();
            $user->setNom($nom_utilisateur);
            $user->setPrenom($prenom_utilisateur);
            $user->setDateDeNaissance($datedenaissance_utilisateur);
            $user->setEmail($email_utilisateur);
            $user->setCompteActif(true);
            $user->setUid($uid);
            $user->setProvenance('ldap');
            $entityManager->persist($user);
            $entityManager->flush();
        }

        return $user;
    }

    



/**
 * @brief Vérifie les permissions d'accès à une demande pour le valideur connecté.
 *
 * @param int $demandeId Identifiant de la demande.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @throws AccessDeniedException Si l'utilisateur n'a pas les droits d'accès.
 * @throws NotFoundHttpException Si la demande est introuvable.
 */


    private function checkUserPermissionForDemande(int $demandeId, EntityManagerInterface $entityManager): void
    {
        $isSuperUser  =  $this->superUserChecker->isSuperUser();
        if ($isSuperUser) {
            // Si l'utilisateur est un super utilisateur, on bypass la vérification.
            return;
        }
        // Récupérer la demande
        $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $uid_valideur = $demande->getUidValideur();
    
        
        // Récupérer l'utilisateur actuellement connecté
        $currentUser = $this->security->getUser();
        $uid_current = $currentUser->getUid();
    
        if (!$currentUser) {
            throw $this->createAccessDeniedException('vous devez être connecté.');
        }
    
        // Comparer les emails
        if ($uid_current !== $uid_valideur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette demande.');
        }
    }


    

    /**
 * @brief Vérifie le statut d'une demande avant d'autoriser une action si le statut est différente de En attente côté Valideur ca veut
 * dire que soit elle est brouillons et donc l'utilisateur ne l'a pas encore validée , soit elle est deja validée ou refusée.
 *
 * @param int $demandeId Identifiant de la demande.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @throws AccessDeniedException Si la demande n'est pas en attente.
 * @throws NotFoundHttpException Si la demande est introuvable.
 */

private function checkStatuts(int $demandeId, EntityManagerInterface $entityManager): void 
{
    $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);
    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }
    if ($demande->getStatuts() !== 'En attente') {
        throw $this->createAccessDeniedException('Vous ne pouvez pas agir sur cette demande');
    }
}











}