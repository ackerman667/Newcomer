<?php

/**
 * @file
 * @brief Contrôleur pour gérer les demandes LDAP.
 *
 * Ce fichier contient le contrôleur principal pour gérer les fonctionnalités
 * liées aux demandes LDAP, y compris la consultation, modification, validation,
 * suppression.
 */

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

use Symfony\Bundle\SecurityBundle\Security;

/**
 * @class StatutsLdapController
 * @brief Contrôleur pour la gestion des demandes LDAP.
 *
 * Ce contrôleur offre diverses fonctionnalités pour gérer les demandes des utilisateurs
 * LDAP, telles que la création, la consultation, la modification, et la validation.
 */
class StatutsLdapController extends AbstractController
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

        $this->isValideur = $this->roleChecker->isUserValideur();
        $this->isSuperUser = $this->superUserChecker->isSuperUser();
    }

   


      /**
     * @brief  page qui Affiche les demandes de l'utilisateur courant. 
     * Il peut les consulter et effectuer des opérations dessus en fonction du statuts
     *
     * @Route("/formulaireldap/mes-demandes", name="mes_demandes")
     *
   
     * @param MonApplication $monApplication Informations sur l'application.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @return Response
     */

    #[Route('formulaireldap/mes-demandes', name: 'mes_demandes')]
public function mesDemandes(
    MonApplication $monApplication,
    EntityManagerInterface $entityManager
): Response {
    $user = $this->security->getUser(); //Récupérer l'utilisateur connecté 
    $uid = $user->getUid(); // Récupération de l'UID utilisateur.
    $isSuperUser  = $this->superUserChecker->isSuperUser();
    $isValideur = $this->roleChecker->isUserValideur();   // Vérification du rôle de valideur.

    
    $userDemandes = $this->getDemandesPourUtilisateur($entityManager, $uid); // Récupération des demandes de l'utilisateur.

    return $this->render('demandes/mes_demandes.html.twig', [
        'mesDemandes' => $userDemandes,
        'monApplication' => $monApplication,
        'uiduser' => $uid,
        'page' => 'mesdemandes',
        'isValideur' => $isValideur,
        'isSuperUser' => $isSuperUser,

    ]);
}

    /**
     * @brief Affiche les demandes à valider pour un valideur.
     *
     * Cette fonction vérifie si l'utilisateur est un valideur et affiche les demandes
     * qui lui sont assignées.
     *
     * @Route("formulaireldap/demandes-a-valider", name="demandes_a_valider")
     *
     * @param MonApplication $monApplication Informations sur l'application.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @return Response
     */
#[Route('formulaireldap/demandes-a-valider', name: 'demandes_a_valider')]
public function demandesAValider(
    MonApplication $monApplication,
    EntityManagerInterface $entityManager
): Response {
    $user = $this->security->getUser();
    $uid = $user->getUid();
    $isSuperUser  = $this->superUserChecker->isSuperUser();

    // Vérifier si l'utilisateur est un valideur
    $isValideur = $this->roleChecker->isUserValideur();


    if (!$isValideur) {  // Bloquer l'accès si non valideur
        throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
    }

    /* Récupérer les demandes assignées au valideur en fonction de l'uid de la personne connecté et 
    de l'uid present dans le champs valideur de la table demandes => Une demande est asocié a un uid valideur */
    $demandesAValider = $this->getDemandesPourValideur($entityManager, $uid);

    return $this->render('demandes/demandes_a_valider.html.twig', [
        'demandesAValider' => $demandesAValider,
        'monApplication' => $monApplication,
        'uiduser' => $uid,
        'page' => 'demandeavalider',
        'isValideur' => $isValideur,
        'isSuperUser' => $isSuperUser,
    ]);
}
#[Route('formulaireldap/admindemandes', name: 'admin_demandes')]
public function superuser(
    MonApplication $monApplication,
    EntityManagerInterface $entityManager,
    SuperUserChecker $superUserChecker // Injection du service pour vérifier les super utilisateurs
): Response {
    // Récupération de l'utilisateur connecté
    $user = $this->security->getUser();
    $uid = $user->getUid();
    $isValideur = $this->roleChecker->isUserValideur();

    // Vérification si l'utilisateur est un super utilisateur
    $isSuperUser  =  $this->superUserChecker->isSuperUser();

    if (!$isSuperUser) {  // Bloquer l'accès si non valideur
        throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
    }

    // Récupération de toutes les demandes pour le super utilisateur
    $demandesAValider = $this->getDemandesSuperValideur($entityManager);


    return $this->render('demandes/demandes_a_valider.html.twig', [
        'monApplication' => $monApplication,
        'uiduser' => $uid,
        'demandesAValider' => $demandesAValider,
        'page' => 'admindemandes',
        'isValideur' => $isValideur,
        'isSuperUser' => $isSuperUser,

    ]);
}



/**
     * @brief Récupère les demandes pour un utilisateur donné.
     *
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @param string $uid UID de l'utilisateur.
     * @return array Liste des demandes de l'utilisateur.
     */
private function getDemandesPourUtilisateur(EntityManagerInterface $entityManager, string $uid): array
{
    // Récupérer l'utilisateur dans la base de données 
    $user_bdd = $entityManager->getRepository(User::class)->findOneBy([
        'uid' => $uid,
        'provenance' => 'ldap'
    ]);

    // Si l'utilisateur n'est pas trouvé, récupérer uniquement les demandes liées au `uid` et ayant le statut 'Suivi dans LEKA'
    if (!$user_bdd) {
        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder
        ->select('d')
        ->from(Demandes::class, 'd')
        ->leftJoin('d.IDutilisateur', 'u')
        ->where('u.uid = :uid')
        ->andWhere('d.statuts = :statut')
        ->setParameter('uid', $uid)
        ->setParameter('statut', 'Suivi dans LEKA')
        ->orderBy("CASE 
        WHEN d.statuts = 'Brouillons' THEN 1
         WHEN d.statuts = 'En attente' THEN 2
        ELSE 3 
    END", 'ASC') 
        ->addOrderBy('d.date', 'DESC') 
        ->addOrderBy('d.heureSoumission', 'DESC'); 
        

        return $queryBuilder->getQuery()->getResult();
    }

    /* si l'utilisateur existe on recupere toute ses demandes => une demande est lié à un utilisateur , 
    on recupere toutes les demandes associés a l'utilisateur connecté et on les classes par statuts et ensuite ordre d'arrivé */

    $queryBuilder = $entityManager->createQueryBuilder();
    $queryBuilder
    ->select('d')
    ->from(Demandes::class, 'd')
    ->leftJoin('d.IDutilisateur', 'u')
    ->where('d.IDutilisateur = :user')
    ->orWhere('(u.uid = :uid AND d.statuts = :statut)')
    ->setParameter('user', $user_bdd)
    ->setParameter('uid', $uid)
    ->setParameter('statut', 'Suivi dans LEKA')
    ->orderBy("CASE 
        WHEN d.statuts = 'Brouillons' THEN 1
         WHEN d.statuts = 'En attente' THEN 2
        ELSE 3 
    END", 'ASC') 
    ->addOrderBy('d.date', 'DESC') 
    ->addOrderBy('d.heureSoumission', 'DESC'); 
    

    return $queryBuilder->getQuery()->getResult();
}


    /**
     * @brief Récupère les demandes assignées à un valideur spécifique.
     *
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @param string $uid UID du valideur.
     * @return array Liste des demandes à valider.
     */
    private function getDemandesPourValideur(EntityManagerInterface $entityManager, string $uid): array
    {
        $statutExclus = 'Brouillons';

        return $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
        ->leftJoin('d.IDutilisateur', 'u') 
        ->where('d.uid_valideur = :uid')
        ->andWhere('d.statuts <> :statutExclus')
        ->andWhere('NOT (u.provenance = :provenance AND u.uid = d.uid_valideur)') 
        ->setParameter('uid', $uid)
        ->setParameter('statutExclus', $statutExclus)
        ->setParameter('provenance', 'ldap')
        ->orderBy("CASE 
            WHEN d.statuts = 'En attente' THEN 1
            ELSE 2 
        END", 'ASC') 
        ->addOrderBy('d.date', 'DESC') 
        ->addOrderBy('d.heureSoumission', 'DESC') 
        ->getQuery()
        ->getResult();
    
    }

    private function getDemandesSuperValideur(EntityManagerInterface $entityManager): array
{
    $statutExclus = 'Brouillons';

    return $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
        ->where('d.statuts <> :statutExclus') // Exclure les demandes avec le statut "Brouillons"
        ->setParameter('statutExclus', $statutExclus)
        ->orderBy("CASE 
            WHEN d.statuts = 'En attente' THEN 1
            ELSE 2 
        END", 'ASC') // Prioriser les demandes "En attente"
        ->addOrderBy('d.date', 'DESC') // Trier par date décroissante
        ->addOrderBy('d.heureSoumission', 'DESC') // Trier par heure décroissante
        ->getQuery()
        ->getResult();
}

    
    




    /* Faire une nouvelle demande crée une entrée dans la table temporaire et stocke les données du formulaire en JSON
    c'est comme ca que les informations sont transmises entre les étapes */

    /**
     * @brief Crée une nouvelle demande et l'insère dans une table temporaire.
     *
     * @Route("/formulaireldap/nouvelle_demande", name="nouvelle_demande_ldap")
     * @param SessionInterface $session Session utilisateur.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @return Response
     */
#[Route('/formulaireldap/nouvelle_demande', name: 'nouvelle_demande_ldap')]
public function nouvelleDemande(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
    $userBdd = $this->findOrCreateLdapUser($entityManager);
    $temporaryData = new TemporaryData();
    $temporaryData->setUser($userBdd);
    $temporaryData->setAction('create'); 
    $temporaryData->setData([]); 
    $temporaryData->setExpiration((new \DateTime())->modify('+24 hours'));
    $entityManager->persist($temporaryData);
    $entityManager->flush();

    
    return $this->redirectToRoute('formulaireldap_etape1', ['token' => $temporaryData->getToken()]);

}


/* On regarde si la personne qui tente de modifier la demande est la meme qui en est à l'origine et si c'est le cas alors on crée une entrée 
dans la table temporaire , et vu que c'est une modification , il y a deja des données disponibles , on les prépares pour pré remplir le formulaire */

 /**
     * @brief Modifie une demande existante.
     *
     * @Route("/formulaireldap/modifier/{id}", name="modifier_demandesldap")
     * @param Request $request Requête HTTP.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @param SessionInterface $session Session utilisateur.
     * @param int $id Identifiant de la demande.
     * @return Response
     */

#[Route('/formulaireldap/modifier/{id}', name: 'modifier_demandesldap')]
public function modifierDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
{
    $this->checkUserPermissionForDemande($id, $entityManager);
    $this->checkStatuts($id, $entityManager);
    $demande = $entityManager->getRepository(Demandes::class)->find($id);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }
    $user = $this->security->getUser();
    $userInformation = new UserInformation();
    $infos_user = $userInformation->getUserInformation($user);
    $email_utilisateur= $infos_user['mail'];
    $user1 = $entityManager->getRepository(User::class)->findOneBy(['email' => $email_utilisateur]);
   
    // Pré-remplir les données pour le formulaire
    $data = [
        'demande_id' => $id,
        'nom' => $user1->getNom(),
        'prenom' => $user1->getPrenom(),
        'email' => $user1->getEmail(),
        'date_de_naissance' => $user1->getDateDeNaissance(),
        'fonction' => $user1->getFonction(),
        'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
        'selectedService' => $demande->getIdService(),
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

    

    
    $userBdd = $this->findOrCreateLdapUser($entityManager); // si il n'existe pas dans la BDD on le crée avant de passer au formulaire

    $temporaryData = new TemporaryData();
    $temporaryData->setUser($userBdd);
    $temporaryData->setAction('modifier'); // Marque comme une modification
    $temporaryData->setData($data); // Stocker les données pré-remplies
    $temporaryData->setExpiration((new \DateTime())->modify('+24 hours'));

    // Sauvegarder dans la base de données
    $entityManager->persist($temporaryData);
    $entityManager->flush();

    // Rediriger vers l'étape 1 avec le token généré
    return $this->redirectToRoute('formulaireldap_etape1', ['token' => $temporaryData->getToken()]);
}
#[Route('/formulaireldap/a/nouvelle_demande', name: 'nouvelle-demande-ldap')]
public function nouvelleDemandeAutre(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
  
  

    $userBdd = $this->findOrCreateLdapUser($entityManager);
    $temporaryData = new TemporaryData();
    $temporaryData->setUser($userBdd);
    $temporaryData->setAction('create'); 
    $temporaryData->setData([]); 
    $temporaryData->setExpiration((new \DateTime())->modify('+24 hours'));

    
    $entityManager->persist($temporaryData);
    $entityManager->flush();

 
    return $this->redirectToRoute('formulaireldap-etape1', ['token' => $temporaryData->getToken()]);
}






#[Route('/formulaireldap/a/modifier/{id}', name: 'modifier_demandespourautre')]
    public function modifierDemandePourAutre(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $this->checkStatuts($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $user1 = $demande->getAutreUtilisateur();

        // $data = $demande->getInfosPersonne();
        // if (isset($data['date_de_naissance']) && is_array($data['date_de_naissance'])) {
        //     $dateString = $data['date_de_naissance']['date']; 
        //     $dateNaissance = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $dateString);
        //     $data['date_de_naissance'] = $dateNaissance; 
        // }
        $data = [
            'demande_id' => $id,
            'nom' => $user1->getNom(),
            'prenom' => $user1->getPrenom(),
            'email' => $user1->getEmail(),
            'date_de_naissance' => $user1->getDateDeNaissance() ? $user1->getDateDeNaissance()->format('Y-m-d') : null,
            'fonction' => $user1->getFonction(),
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'selectedService' => $demande->getIdService(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'statut' => $user1->getStatutPersonne(),
            'date_debut_contrat' => $user1->getDateDebut() ? $user1->getDateDebut()->format('Y-m-d') : null,
            'date_fin_contrat' => $user1->getDateFin() ? $user1->getDateFin()->format('Y-m-d') : null,
            'missions' => $demande->getMissions(),
        ];
        
       
    
     $userBdd = $this->findOrCreateLdapUser($entityManager);
    $temporaryData = new TemporaryData();
    $temporaryData->setUser($userBdd);
    $temporaryData->setAction('modifier'); 
    $temporaryData->setData($data); 
    $temporaryData->setExpiration((new \DateTime())->modify('+24 hours'));

    $entityManager->persist($temporaryData);
    $entityManager->flush();


    return $this->redirectToRoute('formulaireldap-etape1', ['token' => $temporaryData->getToken()]);
    }






    /**
     * @brief Supprime une demande spécifique.
     *
     * @Route("/formulaireldap/supprimer/{id}", name="formulaireldap_supprimer")
     * @param int $id Identifiant de la demande.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @return RedirectResponse
     */

#[Route('/formulaireldap/supprimer/{id}', name: 'formulaireldap_supprimer')]
    public function supprimerDemande($id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $this->checkStatuts($id, $entityManager);
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
    
        return $this->redirectToRoute('mes_demandes');
    }








    #[Route('/formulaireldap/valider/{id}', name: 'valider_demandesldap')]
    public function changerStatut(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id, MailerInterface $mailer): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $this->checkStatuts($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

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
        $token = $demande->getToken();
        $valideur_uid = $demande->getUidValideur();
        $mailValideur = $valideur_uid.'@ac-guadeloupe.fr';

        
    
        $emailMessage = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($mailValideur)
            ->subject('Une nouvelle demande vous a été assignée dans l\'application nouveaux arrivants')
            ->html('<p>Une nouvelle demande vous a été assignée dans l\'application nouveaux arrivants.</p>');
    
        $mailer->send($emailMessage);
    

        return $this->redirectToRoute('mes_demandes');

    }


    #[Route('formulaireldap/demande/consult/{id}', name: 'demande_consult_ldap')]
    public function consult(MonApplication $monApplication, $id, EntityManagerInterface $entityManager): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
       $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
        $ressourcesDecoded = json_decode($ressources->getContenu(), true); // true = tableau associatif

    
        // Faire la distinction si la demande est pour une autre personne ou non
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
            'ressourcesList' => $ressourcesDecoded,
            'valideur' => $valideur,
            'provenance' => 'ldap'

        ]);
    }


  /**
     * @brief Génère un PDF pour une demande spécifique.
     *
     * @Route("/formulaireldap/demande/pdf/{id}", name="demande_pdf_ldap")
     * @param int $id Identifiant de la demande.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @return Response
     */
        #[Route('formulaireldap/demande/pdf/{id}', name: 'demande_pdf_ldap')]
    public function generatePdfldap($id, EntityManagerInterface $entityManager): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
       $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
       
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
    
       
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
        } else {
            
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
    

      $imagePath = $this->getParameter('kernel.project_dir') . '/public/interfaceappli/css/images/10_logoAC_GUADELOUPE_web.png';
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
    
  



    #[Route('/formulaireldap/a/supprimer/{id}', name: 'supprimer_demandespourautre')]
    public function supprimerDemandePourAutre($id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $this->checkStatuts($id, $entityManager);
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

        return $this->redirectToRoute('mes_demandes');
    }

    #[Route('/formulaireldap/a/valider/{id}', name: 'valider_demandespourautre')]
    public function validerDemandePourAutre(MonApplication $monApplication, EntityManagerInterface $entityManager, $id): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $this->checkStatuts($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
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
        $historique->setStatutOperation('Envoi de la demande');

        $entityManager->persist($historique);
        $entityManager->flush();

        $valideur_uid = $demande->getUidValideur();
        $mailValideur = $valideur_uid.'@ac-guadeloupe.fr';
        $emailMessage = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($mailValideur)
            ->subject('Une nouvelle demande vous a été assignée dans l\'application nouveaux arrivants')
            ->html('<p>Une nouvelle demande vous a été assignée dans l\'application nouveaux arrivants.</p>');
    
        $mailer->send($emailMessage);

        return $this->redirectToRoute('mes_demandes');
    }



    
    public function getValideurMail(Demandes $demande): string
    {
        $uidValideur = $demande->getUidValideur(); 
        if ($uidValideur) {
      
            return $uidValideur . '@ac-guadeloupe.fr';
        }
    
       
        return 'noreply@ac-guadeloupe.fr';
    }
    
    

        // Refuser l'accès aux pages nécessitant d'etre valideur
         /**
     * @brief Refuse l'accès aux pages nécessitant le rôle de valideur.
     *
     * Cette fonction vérifie si l'utilisateur connecté est un valideur.
     * Si ce n'est pas le cas, elle lève une exception d'accès refusé.
     *
     * @throws AccessDeniedException Si l'utilisateur n'est pas un valideur.
     */
    private function denyAccessUnlessValideur()
    {
        if ($superUserChecker->isSuperUser()) {
            // Si l'utilisateur est un super utilisateur, on bypass la vérification.
            return;
        }
        if (!$this->isValideur) {
            throw $this->createAccessDeniedException('Vous devez être un valideur pour accéder à cette section.');
        }
    }

        // Rechercher le compte dans la base de données associées à l'utilisateur LDAP , si il n'existe pas  on le crée  
        /**
     * @brief Trouve ou crée un utilisateur LDAP dans la base de données.
     *
     * Cette fonction vérifie si l'utilisateur actuellement connecté existe dans la base.
     * Si l'utilisateur n'existe pas, il est créé avec les informations récupérées via LDAP.
     *
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @return User L'utilisateur LDAP trouvé ou créé.
     * @throws LogicException Si aucun utilisateur n'est connecté.
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



    // Fonction qui permet de vérifier avant chaque action si la personne veut effectuer une action sur sa demande 
     /**
     * @brief Vérifie les permissions d'accès à une demande pour l'utilisateur connecté.
     *
     * Cette fonction s'assure que l'utilisateur connecté est autorisé à accéder
     * ou modifier une demande spécifique.
     *
     * @param int $demandeId Identifiant de la demande.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @throws NotFoundHttpException Si la demande n'existe pas.
     * @throws AccessDeniedException Si l'utilisateur n'a pas les permissions nécessaires.
     */
    private function checkUserPermissionForDemande(int $demandeId, EntityManagerInterface $entityManager): void
{
   
    $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

   
    $userFromDemande = $demande->getIDutilisateur();

    if (!$userFromDemande) {
        throw $this->createAccessDeniedException('Cette demande n\'est pas associée à un utilisateur valide.');
    }

    
    $currentUser = $this->security->getUser();

    if (!$currentUser) {
        throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette demande.');
    }

    
    if ($userFromDemande->getEmail() !== $currentUser->getMail() &&  $userFromDemande->getUid() !== $currentUser->getUid()) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette demande.');
    }
}

// Verifier le statuts de la demande avant d'effectuer une action
private function checkStatuts(int $demandeId, EntityManagerInterface $entityManager): void 
{
    $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);
    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }
    if ($demande->getStatuts() !== 'Brouillons') {
        throw $this->createAccessDeniedException('Vous ne pouvez pas agir sur cette demande car elle est deja validée".');
    }
}

}




