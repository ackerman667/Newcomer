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

use Symfony\Component\Security\Core\Security;


class StatutsLdapController extends AbstractController
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

    #[Route('formulaireldap/listedemandes', name: 'liste_demandes')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        $uid = $user->getUid();

        // Vérifier si l'utilisateur est un valideur (utilisation d'une méthode de service externe)
        $isValideur = $this->roleChecker->isUserValideur();

        // Récupérer les demandes de l'utilisateur courant (pour la section "Mes Demandes")
        $userDemandes = $this->getDemandesPourUtilisateur($entityManager, $uid);

        // Récupérer les demandes assignées au valideur (si l'utilisateur est un valideur)
        $demandesAValider = $isValideur ? $this->getDemandesPourValideur($entityManager, $uid) : [];

        return $this->render('demandes/index.html.twig', [
            'mesDemandes' => $userDemandes,
            'demandesAValider' => $demandesAValider,
            'monApplication' => $monApplication,
            'isValideur' => $isValideur,
        ]);
    }

    /**
     * Récupère les demandes pour un utilisateur classique.
     */
    private function getDemandesPourUtilisateur(EntityManagerInterface $entityManager, string $uid): array
    {
        // Récupérer l'utilisateur correspondant à l'UID dans la table User
        $user_bdd = $entityManager->getRepository(User::class)->findOneBy(['uid' => $uid]);
        
        // Si l'utilisateur n'est pas trouvé, retourner un tableau vide
        if (!$user_bdd) {
            return [];
        }
    
        // Construire la requête pour récupérer les demandes selon les deux critères
        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder
            ->select('d')
            ->from(Demandes::class, 'd')
            ->leftJoin('d.IDutilisateur', 'u') // Joindre la table User via l'IDutilisateur
            ->where('d.IDutilisateur = :user') // Critère basé sur l'utilisateur
            ->orWhere('u.uid = :uid')          // Critère basé sur l'UID
            ->setParameter('user', $user_bdd)
            ->setParameter('uid', $uid);
    
        // Exécuter la requête et renvoyer les résultats
        return $queryBuilder->getQuery()->getResult();
    }
    /**
     * Récupère les demandes assignées à un valideur spécifique.
     */
    private function getDemandesPourValideur(EntityManagerInterface $entityManager, string $uid): array
    {
        $statutExclus = 'Brouillons';

        return $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
            ->where('d.uid_valideur = :uid')
            ->andWhere('d.statuts <> :statutExclus') // Exclure les brouillons
            ->setParameter('uid', $uid)
            ->setParameter('statutExclus', $statutExclus)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les informations de l'utilisateur à partir d'une demande.
     */
    private function getUserDataFromDemande(Demandes $demande): array
    {
        if ($demande->isAutrePersonne()) {
            $infos_personne = $demande->getInfosPersonne();

            // Décoder JSON en tableau si nécessaire
            if (!is_array($infos_personne)) {
                $infos_personne = json_decode($infos_personne, true) ?? [];
            }

            return [
                'nom' => $infos_personne['nom'] ?? '',
                'prenom' => $infos_personne['prenom'] ?? '',
                'email' => $infos_personne['email'] ?? '',
                'date_de_naissance' => $infos_personne['date_de_naissance'] ?? '',
                'fonction' => $infos_personne['fonction'] ?? '',
                'statut' => $infos_personne['statut'] ?? ''
            ];
        } else {
            // Utiliser les informations de l'utilisateur lié à la demande
            $userEntity = $demande->getIDutilisateur();
            return [
                'nom' => $userEntity ? $userEntity->getNom() : '',
                'prenom' => $userEntity ? $userEntity->getPrenom() : '',
                'email' => $userEntity ? $userEntity->getEmail() : '',
            ];
        }
    }



// ----------------------------------------------------------------------------------------------------------------------------------> 
// ----------------------------------------------------------------------------------------------------------------------------------> CRUD VALIDEUR


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
    
        // Créer un historique de la demande
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut('Suivi dans LEKA');
        $historique->setStatutOperation('Envoi de la demande dans LEKA');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);
        $entityManager->flush();
    
        // Récupérer l'email de l'utilisateur ou de la personne cible
       // Récupérer les informations de la demande
    $infosPersonne = $demande->getInfosPersonne();
    
    // Vérifiez si les informations sont déjà un tableau ou non
    if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
        // Décoder le JSON seulement si c'est une chaîne
        $infosPersonne = json_decode($infosPersonne, true);
    }
    
       // Générer le PDF
       $pdfResponse = $this->generatePdf($id, $entityManager);
    
       // Récupérer le contenu du PDF généré
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
    
    // Vérifiez si les informations sont déjà un tableau ou non
    if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
        // Décoder le JSON seulement si c'est une chaîne
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
    
       // Vérifiez si infos_personne est déjà un tableau ou non
    $infosPersonne = $demande->getInfosPersonne();
    if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
        // Décoder le JSON seulement si c'est une chaîne
        $infosPersonne = json_decode($infosPersonne, true);
    }
    
    // Récupérez l'email en fonction du type de demande
    $email = $demande->isAutrePersonne() ? $infosPersonne['email'] ?? '' : $demande->getIDutilisateur()->getEmail();
    
    
        $emailMessage = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($email)
            ->subject('Votre demande a reçu un commentaire')
            ->html('<p>Votre demande a reçu un commentaire : ' . $commentaire . '</p>');
    
        $mailer->send($emailMessage);
    
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
    
        // Vérifier si la demande est pour une autre personne
        if ($demande->isAutrePersonne()) {
            $infos_personne = $demande->getInfosPersonne();
    
            // Si infos_personne n'est pas déjà un tableau, décoder JSON
            if (!is_array($infos_personne)) {
                $user = json_decode($infos_personne, true);
            } else {
                $user = $infos_personne;
            }
        } else {
            // Sinon, récupérer les informations de l'utilisateur associé à la demande
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
    
        // Vérifier si la demande est valide
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
    
        // Vérifier si la demande est faite pour une autre personne
        if ($demande->isAutrePersonne()) {
            // Récupérer les informations à partir du JSON
            $infosPersonne = $demande->getInfosPersonne();
    
            // Vérifier que le contenu est une chaîne JSON avant d'appeler json_decode
            if (is_string($infosPersonne)) {
                $userInfos = json_decode($infosPersonne, true);
            } else {
                // Si ce n'est pas une chaîne, on considère que c'est déjà un tableau
                $userInfos = $infosPersonne;
            }
    
            if (!empty($userInfos['date_de_naissance']) && is_array($userInfos['date_de_naissance'])) {
                $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d H:i:s.u', $userInfos['date_de_naissance']['date']);
            }
        } else {
            // Récupérer les informations de l'utilisateur lié
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
    
        // Configurer Dompdf selon vos besoins
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
    
        // Récupérer le contenu HTML de votre template
        $html = $this->renderView('visualiser-demandes/pdf.html.twig', [
            'demande' => $demande,
            'user' => $userInfos,
            'ressources' => $ressources,
            'imageSrc' => $imageSrc,
            'valideur' => $valideur
        ]);
    
        // Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
    
        // Envoyer le PDF au navigateur
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="demande.pdf"',
        ]);
    }

// ----------------------------------------------------------------------------------------------------------------------------------> PARTIE COMMUNE
// ----------------------------------------------------------------------------------------------------------------------------------> PARTIE COMMUNE
// ----------------------------------------------------------------------------------------------------------------------------------> PARTIE COMMUNE
// ----------------------------------------------------------------------------------------------------------------------------------> PARTIE COMMUNE
// ----------------------------------------------------------------------------------------------------------------------------------> PARTIE COMMUNE
// ----------------------------------------------------------------------------------------------------------------------------------> PARTIE COMMUNE
#[Route('/formulaireldap/nouvelle_demande', name: 'nouvelle_demande_ldap')]
public function nouvelleDemande(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
    // Réinitialiser les données de la session pour démarrer une nouvelle demande
    $session->remove('form_data');
    $session->remove('demande_id');

    $session->set('nouvelle_demande', true);
    

    return $this->redirectToRoute('formulaireldap_etape1');
}
#[Route('/formulaireldap/a/nouvelle_demande', name: 'nouvelle-demande-ldap')]
public function nouvelleDemandeAutre(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
    // Réinitialiser les données de la session pour démarrer une nouvelle demande
    $session->remove('form_data');
    $session->remove('demande_id');

    $session->set('nouvelle_demande', true);
    

    return $this->redirectToRoute('formulaireldap-etape1');
}


#[Route('/formulaireldap/supprimer/{id}', name: 'formulaireldap_supprimer')]
    public function supprimerDemande($id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
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
    
        $this->addFlash('success', 'La demande a été supprimée avec succès.');
    
        return $this->redirectToRoute('liste_demandes');
    }






    #[Route('/formulaireldap/modifier/{id}', name: 'modifier_demandesldap')]
    public function modifierDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $user = $this->security->getUser();
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user);
        $email_utilisateur= $infos_user['mail'];
        $user1 = $entityManager->getRepository(User::class)->findOneBy(['email' => $email_utilisateur]);
        $token = $demande->getToken();

        // Pré-remplir les données pour le formulaire
        $data = [
            'nom' => $user1->getNom(),
            'prenom' => $user1->getPrenom(),
            'email' => $user1->getEmail(),
            'date_de_naissance' => $user1->getDateDeNaissance(),
            'fonction' => $user1->getFonction(),
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'statut' => $user1->getStatutPersonne(),
            'fonction' => $user1->getFonction(),
            'date_debut_contrat' => $user1->getDateDebut(),
            'date_fin_contrat' => $user1->getDateFin(),
            'missions' => $demande->getMissions(),
        ];

        $session->set('form_data', $data);
        $session->set('demande_id', $id);

        return $this->redirectToRoute('formulaireldap_etape1', ['id' => $id]);
    }


    #[Route('/formulaireldap/valider/{id}', name: 'valider_demandesldap')]
    public function changerStatut(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

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
        $token = $demande->getToken();
    

        return $this->redirectToRoute('liste_demandes');

    }


    #[Route('formulaireldap/demande/consult/{token}', name: 'demande_consult_ldap')]
    public function consult(MonApplication $monApplication, $token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
    
        // Faire la distinction si la demande est pour une autre personne ou non
        if ($demande->isAutrePersonne()) {
            // Récupérer les informations de l'autre personne à partir du JSON
            $infos_personne = $demande->getInfosPersonne();
            if (!is_array($infos_personne)) {
                $infos_personne = json_decode($infos_personne, true) ?? [];
            }
    
            // Formatage de la date de naissance
            if (isset($infos_personne['date_de_naissance']) && is_array($infos_personne['date_de_naissance'])) {
                $dateString = $infos_personne['date_de_naissance']['date'];
                $dateNaissance = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $dateString);
                $infos_personne['date_de_naissance'] = $dateNaissance;
            }
    
            $user = $infos_personne; // Utiliser les infos du JSON pour l'affichage
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



        #[Route('formulaireldap/demande/pdf/{token}', name: 'demande_pdf_ldap')]
    public function generatePdfldap($token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
    
        // Vérifier si la demande est valide
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
    
        // Vérifier si la demande est faite pour une autre personne
        if ($demande->isAutrePersonne()) {
            // Récupérer les informations à partir du JSON
            $infosPersonne = $demande->getInfosPersonne();
    
            // Vérifier que le contenu est une chaîne JSON avant d'appeler json_decode
            if (is_string($infosPersonne)) {
                $userInfos = json_decode($infosPersonne, true);
            } else {
                // Si ce n'est pas une chaîne, on considère que c'est déjà un tableau
                $userInfos = $infosPersonne;
            }
    
            // Convertir la date de naissance si elle est présente et au bon format
            if (!empty($userInfos['date_de_naissance']) && is_array($userInfos['date_de_naissance'])) {
                $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d H:i:s.u', $userInfos['date_de_naissance']['date']);
            } elseif (!empty($userInfos['date_de_naissance']) && is_string($userInfos['date_de_naissance'])) {
                // Si la date est une chaîne, essayez de la convertir
                $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d', $userInfos['date_de_naissance']);
            }
        } else {
            // Récupérer les informations de l'utilisateur lié
            $user = $demande->getIDutilisateur();
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
        }
        $valideur = $demande->getUidValideur();
    

        $imagePath = 'C:\Users\nbarbeu\newcomer\public\interfaceappli\css\images\logoaca\academie.png'; 
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageSrc = 'data:image/png;base64,' . $imageData;
    
        // Configurer Dompdf selon vos besoins
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
    
        // Récupérer le contenu HTML de votre template
        $html = $this->renderView('visualiser-demandes/pdf.html.twig', [
            'demande' => $demande,
            'user' => $userInfos,
            'ressources' => $ressources,
            'imageSrc' => $imageSrc,
            'valideur' => $valideur
        ]);
    
        // Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);
    
        // Définir le format du papier et l'orientation
        $dompdf->setPaper('A4', 'portrait');
    
        // Rendre le PDF
        $dompdf->render();
    
        // Envoyer le PDF au navigateur
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="demande.pdf"',
        ]);
    }
    
  

    // ----------------------------------------------------------------------------------------------------------------------------------> 
// ----------------------------------------------------------------------------------------------------------------------------------> 
// ---------------------------------------------------------------------------------------------------------------------------------->  AUTRE

#[Route('/formulaireldap/a/modifier/{id}', name: 'modifier_demandespourautre')]
    public function modifierDemandePourAutre(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $data = $demande->getInfosPersonne();
        if (isset($data['date_de_naissance']) && is_array($data['date_de_naissance'])) {
            $dateString = $data['date_de_naissance']['date']; // Extraction de la chaîne de date
            $dateNaissance = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $dateString);
            $data['date_de_naissance'] = $dateNaissance; // Remplacer dans le tableau de données
        }
        $session->set('form_data', $data);
        $session->set('demande_id', $id);

        return $this->redirectToRoute('formulaireldap-etape1', ['id' => $id]);
    }

    #[Route('/formulaireldap/a/supprimer/{id}', name: 'supprimer_demandespourautre')]
    public function supprimerDemandePourAutre($id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
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

        $this->addFlash('success', 'La demande a été supprimée avec succès.');

        return $this->redirectToRoute('liste_demandes');
    }

    #[Route('/formulaireldap/a/valider/{id}', name: 'valider_demandespourautre')]
    public function validerDemandePourAutre(MonApplication $monApplication, EntityManagerInterface $entityManager, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $demande->setStatuts('En attente');
        $entityManager->persist($demande);

        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut('Envoyée');
        $historique->setDate(new \DateTime('now', $this->timezone));
        $historique->setStatutOperation('Envoi de la demande');

        $entityManager->persist($historique);
        $entityManager->flush();

        return $this->redirectToRoute('liste_demandes');
    }



    
    public function getValideurMail(Demandes $demande): string
    {
        $uidValideur = $demande->getUidValideur(); // Récupère l'UID du valideur
        if ($uidValideur) {
            // Génère l'adresse e-mail en ajoutant le domaine
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