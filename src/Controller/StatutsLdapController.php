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

use Symfony\Bundle\SecurityBundle\Security;


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
    public function index(SessionInterface $session, MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {
        $session->remove('form_data');
        $session->remove('demande_id');
        $session->remove('nouvelle_demande');
        $session->remove('dossiers_partages');
        $session->remove('_csrf/https-demande_etape1_form');
        $session->remove('_csrf/https-demande_etape2_form');
        $session->remove('_csrf/https-demande_etape3_form');
        $session->remove('nom_service_selectionne');
        $session->remove('nom_valideur');
        $user = $this->security->getUser();
        $uid = $user->getUid();

        // Vérifier si l'utilisateur est un valideur 
        $isValideur = $this->roleChecker->isUserValideur();

        // Récupérer les demandes de l'utilisateur courant (pour la section "Mes Demandes")
        $userDemandes = $this->getDemandesPourUtilisateur($entityManager, $uid);

        // Récupérer les demandes assignées au valideur (si l'utilisateur est un valideur)
        $demandesAValider = $isValideur ? $this->getDemandesPourValideur($entityManager, $uid) : [];

        $sessionData = $session->all();

        dump($sessionData);

        return $this->render('demandes/index.html.twig', [
            'mesDemandes' => $userDemandes,
            'demandesAValider' => $demandesAValider,
            'monApplication' => $monApplication,
            'isValideur' => $isValideur,
            'uiduser' => $uid,
        ]);
    }

    /**
     * Récupère les demandes pour un utilisateur classique.
     */
    private function getDemandesPourUtilisateur(EntityManagerInterface $entityManager, string $uid): array
    {
        // Récupérer l'utilisateur correspondant à l'UID dans la table User
        $user_bdd = $entityManager->getRepository(User::class)->findOneBy([
            'uid' => $uid,
            'provenance' => 'ldap'
        ]);
        
        
        // Si l'utilisateur n'est pas trouvé, retourner un tableau vide
        if (!$user_bdd) {
            $queryBuilder = $entityManager->createQueryBuilder();
            $queryBuilder
            ->select('d')
            ->from(Demandes::class, 'd')
            ->leftJoin('d.IDutilisateur', 'u') 
            ->where('u.uid = :uid') 
            ->andWhere('d.statuts = :statut')         
            ->setParameter('uid', $uid)
            ->setParameter('statut', 'Suivi dans LEKA');
            return $queryBuilder->getQuery()->getResult();

        }
    
        // Construire la requête pour récupérer les demandes selon les deux critères
        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder
            ->select('d')
            ->from(Demandes::class, 'd')
            ->leftJoin('d.IDutilisateur', 'u') 
            ->where('d.IDutilisateur = :user') 
            ->orWhere('(u.uid = :uid AND d.statuts = :statut)')          
            ->setParameter('user', $user_bdd)
            ->setParameter('uid', $uid)
            ->setParameter('statut', 'Suivi dans LEKA');
    
     
        return $queryBuilder->getQuery()->getResult();
    }
    /**
     * Récupère les demandes assignées à un valideur spécifique.
     */
    private function getDemandesPourValideur(EntityManagerInterface $entityManager, string $uid): array
    {
        $statutExclus = 'Brouillons';
    
        return $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
            ->leftJoin('d.IDutilisateur', 'u') // Jointure avec l'utilisateur
            ->where('d.uid_valideur = :uid')
            ->andWhere('d.statuts <> :statutExclus')
            ->andWhere('NOT (u.provenance = :provenance AND u.uid = d.uid_valideur)') // Condition supplémentaire
            ->setParameter('uid', $uid)
            ->setParameter('statutExclus', $statutExclus)
            ->setParameter('provenance', 'ldap') // Paramètre pour la provenance
            ->getQuery()
            ->getResult();
    }
    

    /**
     * Récupère les informations de l'utilisateur à partir d'une demande.
     */
    private function getUserDataFromDemande(Demandes $demande): array
    {
        if ($demande->isAutrePersonne()) {
            $userautre = $demande->getAutreUtilisateur();
         

            return [
                'nom' => $userautre ? $userautre->getNom() : '',
                'prenom' => $userautre ? $userautre->getPrenom() : '',
                'email' => $userautre? $userautre>getEmail() : '',
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




#[Route('/formulaireldap/nouvelle_demande', name: 'nouvelle_demande_ldap')]
public function nouvelleDemande(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
    
    $session->remove('form_data');
    $session->remove('demande_id');

    $session->set('nouvelle_demande', true);
    

    return $this->redirectToRoute('formulaireldap_etape1');
}
#[Route('/formulaireldap/a/nouvelle_demande', name: 'nouvelle-demande-ldap')]
public function nouvelleDemandeAutre(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
  
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



        #[Route('formulaireldap/demande/pdf/{token}', name: 'demande_pdf_ldap')]
    public function generatePdfldap($token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
    
       
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
    
  


#[Route('/formulaireldap/a/modifier/{id}', name: 'modifier_demandespourautre')]
    public function modifierDemandePourAutre(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $data = $demande->getInfosPersonne();
        if (isset($data['date_de_naissance']) && is_array($data['date_de_naissance'])) {
            $dateString = $data['date_de_naissance']['date']; 
            $dateNaissance = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $dateString);
            $data['date_de_naissance'] = $dateNaissance; 
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

        return $this->redirectToRoute('liste_demandes');
    }



    
    public function getValideurMail(Demandes $demande): string
    {
        $uidValideur = $demande->getUidValideur(); 
        if ($uidValideur) {
      
            return $uidValideur . '@ac-guadeloupe.fr';
        }
    
       
        return 'noreply@ac-guadeloupe.fr';
    }
    
    


    private function denyAccessUnlessValideur()
    {
        if (!$this->isValideur) {
            throw $this->createAccessDeniedException('Vous devez être un valideur pour accéder à cette section.');
        }
    }




}