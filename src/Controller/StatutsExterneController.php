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



    /**
 * @brief Affiche la liste des demandes pour l'utilisateur connecté.
 *
 * Cette méthode récupère et affiche toutes les demandes liées à l'utilisateur
 * connecté dans une interface dédiée.
 *
 * @Route('formulaireext/statuts', name='demande_externe')
 *
 * @param Request $request La requête HTTP courante.
 * @param SessionInterface $session Gestion de session utilisateur.
 * @param MonApplication $monApplication Informations sur l'application.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return Response La page affichant les demandes externes.
 */

    #[Route('formulaireext/statuts', name: 'demande_externe')]
    public function index( Request $request , SessionInterface $session, MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessUserIsActive();
         $user = $this->getUser();
        $demandes = $this->getDemandesPourUtilisateur($entityManager, $user);


        return $this->render('demandes/demandes_externe.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
            'user' => $user,
        ]);
    }


    /**
 * @brief Affiche les détails d'une demande spécifique.
 *
 * Cette méthode permet de visualiser toutes les informations relatives à une demande donnée,
 * y compris les ressources associées et le valideur.
 *
 * @Route('formulaireext/demande/consult/{id}', name='demande_consult')
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


    #[Route('formulaireext/demande/consult/{id}', name: 'demande_consult')]
    public function consult(MonApplication $monApplication, $id, EntityManagerInterface $entityManager): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $valideur = $demande->getUidValideur();


        


      

        $user = $demande->getIDutilisateur();
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
        $ressourcesDecoded = json_decode($ressources->getContenu(), true);

        return $this->render('visualiser-demandes/visualiser.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'monApplication' => $monApplication,
            'ressources' => $ressources,
            'ressourcesList' => $ressourcesDecoded,
            'valideur' => $valideur,
            'provenance' => 'externe'

        ]);
    }


/**
 * @brief Récupère les demandes associées à un utilisateur spécifique.
 *
 * Cette méthode permet de récupérer toutes les demandes associées à l'utilisateur
 * connecté, triées par statut et date.
 *
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param User $user L'utilisateur pour lequel les demandes doivent être récupérées.
 *
 * @return array Retourne un tableau des demandes trouvées, triées comme suit :
 * - En premier, les demandes ayant le statut "Brouillons".
 * - Ensuite, celles avec le statut "En attente".
 * - Enfin, les autres statuts, le tout classé par date de soumission descendante.
 *
 * @details
 * - Utilise un `QueryBuilder` pour construire la requête de manière flexible.
 * - Trie les demandes selon plusieurs critères pour garantir que les brouillons
 *   et les demandes en attente soient affichées en priorité.
 * - Les demandes sont retournées sous forme d'un tableau d'objets `Demandes`.
 *
 * @throws Exception Si une erreur inattendue survient lors de la récupération des données.
 
 */


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

  /**
 * @brief Génère un PDF pour une demande spécifique.
 *
 * Cette méthode génère et affiche un PDF contenant les informations relatives à une demande.
 *
 * @Route('formulaireext/demande/pdf/{id}', name='demande_pdf')
 *
 * @param MonApplication $monApplication Informations sur l'application.
 * @param int $id Identifiant de la demande.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return Response Le fichier PDF généré en réponse HTTP.
 *
 * @throws AccessDeniedException Si l'utilisateur n'a pas les droits d'accès à cette demande.
 * @throws NotFoundHttpException Si la demande est introuvable.
 */


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
    
    

    /**
 * @brief Crée une nouvelle demande pour l'utilisateur connecté.
 *
 * Cette méthode initialise une nouvelle demande et la stocke temporairement pour
 * permettre à l'utilisateur de compléter le formulaire.
 *
 * @Route('formulaireext/nouvelle_demande', name='nouvelle_demande')
 *
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return Response Une redirection vers la première étape de la création de demande.
 */

    #[Route('formulaireext/nouvelle_demande', name: 'nouvelle_demande')]
    public function nouvelleDemande(EntityManagerInterface $entityManager ): Response
    {

    

         $user = $this->getUser();
        
        $temporaryData = new TemporaryData();
        $temporaryData->setUser($user);
        // $temporaryData->setData($data);
        $temporaryData->setAction('create'); 
        $temporaryData->setData([]); 
        $temporaryData->setExpiration((new \DateTime())->modify('+24 hours'));
        $entityManager->persist($temporaryData);
        $entityManager->flush();



    
        
        return $this->redirectToRoute('formulaireexterne_etape1', [
            'uuid' => $temporaryData->getToken(),
        ]);
        
    }
    
/**
 * @brief Supprime une demande spécifique.
 *
 * Cette méthode supprime une demande, ses historiques, et les ressources associées,
 * après vérification des droits d'accès de l'utilisateur.
 *
 * @Route('formulaireext/supprimer/{id}', name='formulaireexterne_supprimer')
 *
 * @param Request $request La requête HTTP courante.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param int $id Identifiant de la demande à supprimer.
 *
 * @return Response Une redirection vers la liste des demandes externes.
 *
 * @throws AccessDeniedException Si l'utilisateur n'a pas les droits d'accès à cette demande.
 * @throws NotFoundHttpException Si la demande est introuvable.
 */

    #[Route('formulaireext/supprimer/{id}', name: 'formulaireexterne_supprimer')]
    public function supprimerDemande(Request $request, EntityManagerInterface $entityManager, $id): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $this->checkStatuts($id, $entityManager);
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


    /**
 * @brief Modifie une demande existante.
 *
 * Cette méthode permet de préremplir les informations d'une demande et d'initier
 * le processus de modification.
 *
 * @Route('formulaireext/modifier/{id}', name='modifier_demandes')
 *
 * @param MonApplication $monApplication Informations sur l'application.
 * @param Request $request La requête HTTP courante.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param SessionInterface $session Gestion de session utilisateur.
 * @param int $id Identifiant de la demande à modifier.
 * @param MailerInterface $mailer Service de messagerie pour notifier l'utilisateur.
 *
 * @return Response Une redirection vers la première étape de la modification.
 */

    #[Route('formulaireext/modifier/{id}', name: 'modifier_demandes')]
    public function modifierDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id, MailerInterface $mailer): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $this->checkStatuts($id, $entityManager);
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
            'selectedService' => $demande->getIdService(),
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
        $temporaryData->setExpiration((new \DateTime())->modify('+24 hours'));
        $entityManager->persist($temporaryData);
        $entityManager->flush();

        return $this->redirectToRoute('formulaireexterne_etape1', [
            'uuid' => $temporaryData->getToken(),
        ]);
        
    }


    /**
 * @brief Valide et soumet une demande.
 *
 * Cette méthode change le statut d'une demande à "En attente" et enregistre
 * un historique de l'opération. Une notification par e-mail est envoyée.
 *
 * @Route('formulaireext/valider/{id}', name='valider_demandes')
 *
 * @param MonApplication $monApplication Informations sur l'application.
 * @param Request $request La requête HTTP courante.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param SessionInterface $session Gestion de session utilisateur.
 * @param int $id Identifiant de la demande à valider.
 * @param MailerInterface $mailer Service de messagerie pour notifier l'utilisateur.
 *
 * @return Response Une redirection vers la liste des demandes externes.
 */

    #[Route('formulaireext/valider/{id}', name: 'valider_demandes')]
    public function validerDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id,MailerInterface $mailer): Response
    {
        $this->checkUserPermissionForDemande($id, $entityManager);
        $this->checkStatuts($id, $entityManager);
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
        $valideur_uid = $demande->getUidValideur();
        $mailValideur = $valideur_uid.'@ac-guadeloupe.fr';

        
    
        $emailMessage = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($mailValideur)
            ->subject('Une nouvelle demande vous a été assignée dans l\'application nouveaux arrivants')
            ->html('<p>Une nouvelle demande vous a été assignée dans l\'application nouveaux arrivants.</p>');
    
        $mailer->send($emailMessage);

        
      
    
       

        return $this->redirectToRoute('demande_externe');
    }


    /**
 * @brief Vérifie si l'utilisateur a les permissions pour accéder à une demande.
 *
 * Cette méthode s'assure que l'utilisateur connecté est le propriétaire de la demande.
 *
 * @param int $demandeId Identifiant de la demande.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @throws AccessDeniedException Si l'utilisateur n'a pas les droits d'accès.
 * @throws NotFoundHttpException Si la demande est introuvable.
 */

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

/**
 * @brief Vérifie le statut d'une demande avant d'effectuer une action.
 *
 * Cette méthode s'assure qu'une demande est encore à l'état de "Brouillons"
 * avant d'autoriser des modifications ou des suppressions.
 *
 * @param int $demandeId Identifiant de la demande.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @throws AccessDeniedException Si la demande n'est plus à l'état de brouillon.
 * @throws NotFoundHttpException Si la demande est introuvable.
 */

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

private function denyAccessUnlessUserIsActive(): void
{
    $user = $this->getUser();

    if (!$user || !$user instanceof User) {
        throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette ressource.');
    }

    if (!$user->isCompteActif()) {
        throw $this->createAccessDeniedException('Votre compte n\'est pas encore activé. Veuillez vérifier votre boîte mail pour l\'activer.');
    }
}




    



}
