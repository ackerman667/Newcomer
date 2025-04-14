<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\TemporaryData;
use App\Service\UserRoleChecker;
use App\Entity\Demandes;
use App\Entity\Ressources;
use App\Service\SuperUserChecker;
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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class ModificationValideurController extends AbstractController

{
    private $timezone;
    private $security;
    private $roleChecker;
    private $superUserChecker;
    public function __construct(Security $security, UserRoleChecker $roleChecker, SuperUserChecker $superUserChecker)
    {
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
        $this->security = $security;
        $this->roleChecker = $roleChecker;
        $this->isValideur = $this->roleChecker->isUserValideur();
        $this->superUserChecker = $superUserChecker;
        $this->isSuperUser = $this->superUserChecker->isSuperUser();
       
    }




  /**
     * Modifier la première étape d'une demande.
     *
     * @Route('formulaireldap/modifierdemandes/etape1/{id}/{token}', name: 'modifier_demandesvalideur_etape1')
     *
     * @param int $id L'identifiant de la demande à modifier.
     * @param string $token Le token de données temporaires associé à la demande.
     * @param Request $request La requête HTTP actuelle.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités pour les opérations en base de données.

     * @param MonApplication $monApplication Classe personnalisée pour gérer l'application.
     * @return Response Vue rendue pour la première étape de modification de la demande.
     */
#[Route('formulaireldap/modifierdemandes/etape1/{id}/{token}', name: 'modifier_demandesvalideur_etape1')]
public function editDemandeEtape1(int $id, string $token, Request $request, EntityManagerInterface $entityManager,  MonApplication $monApplication): Response
{
    $referer = $request->query->get('referer');
    // Vérifie si l'utilisateur est un valideur
    if (!$this->isValideurOrSuperUser()) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette page.');
    }

    // Vérifie si il est bien le valideur associé a la demande
    $this->checkUserPermissionForDemande($id, $entityManager);


    $demande = $entityManager->getRepository(Demandes::class)->find($id);
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
    $userLdap = $this->security->getUser();

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }
    if ($temporaryData->getUser()->getUid() !== $userLdap->getUid()) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette demande.');
    }

    if ($demande->isAutrePersonne()) {
        
        $user_autre= $demande->getAutreUtilisateur();
        $data = [
            'nom' => $user_autre ? $user_autre->getNom() : '',
            'prenom' => $user_autre ? $user_autre->getPrenom() : '',
            'email' => $user_autre ? $user_autre->getEmail() : '',
            'date_de_naissance' => $user_autre ? $user_autre->getDateDeNaissance() : '',
            'fonction' => $user_autre ? $user_autre->getFonction() : '',
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'selectedService' => $demande->getIdService(),
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'date_debut_contrat' => $user_autre ? $user_autre->getDateDebut() : null,
            'date_fin_contrat' => $user_autre ? $user_autre->getDateFin() : null,
            'statut' => $user_autre ? $user_autre->getStatutPersonne() : '',
            'missions' => $demande->getMissions(),
        ];
    } else {
        $user = $demande->getIDutilisateur();
        $data = [
            'nom' => $user ? $user->getNom() : '',
            'prenom' => $user ? $user->getPrenom() : '',
            'email' => $user ? $user->getEmail() : '',
            'date_de_naissance' => $user ? $user->getDateDeNaissance() : '',
            'fonction' => $user ? $user->getFonction() : '',
            'selectedService' => $demande->getIdService(),
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'date_debut_contrat' => $user ? $user->getDateDebut() : null,
            'date_fin_contrat' => $user ? $user->getDateFin() : null,
            'statut' => $user ? $user->getStatutPersonne() : '',
            'missions' => $demande->getMissions(),
        ];
    }

    $form = $this->createForm(DemandeEtape1FormType::class, $data);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->getData();
        $temporaryData->setData($form->getData());
        $entityManager->flush();

        return $this->redirectToRoute('modifier_demandesvalideur_etape2', [
            'id' => $id,
            'token' => $token,
        ]);
    }

    return $this->render('valideur/modifier_etape1.html.twig', [
        'form' => $form->createView(),
        'monApplication' => $monApplication,
        'demande' => $demande,
        'token' => $token,
        'referer' => $referer,
        
    ]);
}


/**
     * Modifier la deuxième étape d'une demande.
     *
     * @Route('formulaireldap/modifierdemandes/etape1/{id}/{token}', name: 'modifier_demandesvalideur_etape1')
     *
     * @param int $id L'identifiant de la demande à modifier.
     * @param string $token Le token de données temporaires associé à la demande.
     * @param Request $request La requête HTTP actuelle.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités pour les opérations en base de données.

     * @param MonApplication $monApplication Classe personnalisée pour gérer l'application.
     * @return Response Vue rendue pour la première étape de modification de la demande.
     */
    #[Route('formulaireldap/modifierdemandes/etape2/{id}/{token}', name: 'modifier_demandesvalideur_etape2')]
    public function editDemandeEtape2(int $id, string $token, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, HttpClientInterface $httpClient, MonApplication $monApplication): Response
    {
        if (!$this->isValideurOrSuperUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette page.');
        }
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
 
$temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);

// Extraire les données stockées dans l'objet TemporaryData.
$data = $temporaryData->getData();

// Vérification et conversion de la date de naissance si elle est définie.
// Si la date est au format tableau avec une clé 'date', on la convertit en un objet DateTime.
// Si elle est sous forme de chaîne (format string), elle est également convertie en DateTime.
if (!empty($data['date_de_naissance'])) {
    if (is_array($data['date_de_naissance']) && isset($data['date_de_naissance']['date'])) {
        $data['date_de_naissance'] = new \DateTime($data['date_de_naissance']['date']);
    } elseif (is_string($data['date_de_naissance'])) {
        $data['date_de_naissance'] = new \DateTime($data['date_de_naissance']);
    }
}

// Vérification et conversion de la date de début du contrat si elle est définie.
// Si la date est au format tableau avec une clé 'date', elle est transformée en un objet DateTime.
// Si elle est au format string, elle est convertie directement en DateTime.
if (!empty($data['date_debut_contrat'])) {
    if (is_array($data['date_debut_contrat']) && isset($data['date_debut_contrat']['date'])) {
        $data['date_debut_contrat'] = new \DateTime($data['date_debut_contrat']['date']);
    } elseif (is_string($data['date_debut_contrat'])) {
        $data['date_debut_contrat'] = new \DateTime($data['date_debut_contrat']);
    }
}

// Vérification et conversion de la date de fin du contrat si elle est définie.
// Comme pour les autres champs de date, la conversion est effectuée en fonction du format (tableau ou chaîne).
if (!empty($data['date_fin_contrat'])) {
    if (is_array($data['date_fin_contrat']) && isset($data['date_fin_contrat']['date'])) {
        $data['date_fin_contrat'] = new \DateTime($data['date_fin_contrat']['date']);
    } elseif (is_string($data['date_fin_contrat'])) {
        $data['date_fin_contrat'] = new \DateTime($data['date_fin_contrat']);
    }
}



        $userLdap = $this->security->getUser();
        if ($temporaryData->getUser()->getUid() !== $userLdap->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette demande.');
        }

        $apiUrl = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/services';
        $apiToken = 'b97b055g210125afb4c5f507dc823958ff18dfa56a12c7n12agch8db58e21767';
        $response = $httpClient->request('GET', $apiUrl, [
            'headers' => [
                'x-auth-token' => $apiToken,
                'Accept' => 'application/json',
            ],
        ]);

        $services = $response->toArray();
        $servicesTree = $this->buildTree($services);
        $servicesDropdownData = $this->transformServicesForDropdown($servicesTree);

        $form = $this->createForm(DemandeEtape2FormType::class, $data, [
            'services' => $servicesDropdownData,
        ]);
        if (array_key_exists('selectedService', $data) && $data['selectedService']) {
            $form->get('selectedService')->setData($data['selectedService']);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updatedData = $form->getData();
            $temporaryData->setData($updatedData);
            // Identifie le service sélectionné
            $selectedServiceId = $form->get('selectedService')->getData();


             // Parcourt la liste des services pour trouver celui sélectionné par l'utilisateur
             foreach ($services as $service) {
                // Vérifie si l'ID du service actuel correspond à l'ID du service sélectionné dans le formulaire
                if ($service['id_service'] == $selectedServiceId) {
                    
                    // Enregistre le nom du service sélectionné dans les données mises à jour
                    $updatedData['nom_service_selectionne'] = $service['service'];
                    
                    // Enregistre les dossiers partagés associés au service sélectionné, s'ils existent
                    // Si le champ 'dossiers_partages' n'existe pas dans les données du service, une liste vide est utilisée par défaut
                    $updatedData['dossiers_partages'] = $service['dossiers_partages'] ?? [];
                    
                    // Arrête la boucle une fois que le service correspondant est trouvé pour éviter des itérations inutiles
                    break;
                }
}
            $apiUrlSecond = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/valideur/' . $selectedServiceId;
            $responseSecond = $httpClient->request('GET', $apiUrlSecond, [
                'headers' => [
                    'x-auth-token' => $apiToken,
                    'Accept' => 'application/json',
                ],
            ]);

        $apiDataSecond = $responseSecond->toArray();
        $updatedData['nom_valideur'] = $apiDataSecond[0]['valideur'] ?? null;
        $temporaryData->setData($updatedData);
        $entityManager->flush();
    

        return $this->redirectToRoute('modifier_demandesvalideur_etape3', [
            'id' => $id,
            'token' => $token,
        ]);
        }

        return $this->render('valideur/modifier_etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'demande' => $demande,
            'token' => $token,

        ]);
    }




/**
     * Modifier la toisième étape d'une demande.
     *
     * @Route('formulaireldap/modifierdemandes/etape3/{id}/{token}', name: 'modifier_demandesvalideur_etape1')
     *
     * @param int $id L'identifiant de la demande à modifier.
     * @param string $token Le token de données temporaires associé à la demande.
     * @param Request $request La requête HTTP actuelle.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités pour les opérations en base de données.

     * @param MonApplication $monApplication Classe personnalisée pour gérer l'application.
     * @return Response Vue rendue pour la première étape de modification de la demande.
     */
    #[Route('formulaireldap/modifierdemandes/etape3/{id}/{token}', name: 'modifier_demandesvalideur_etape3')]
    public function editDemandeEtape3(int $id, string $token, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, MailerInterface $mailer, MonApplication $monApplication): Response
    {
        if (!$this->isValideurOrSuperUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette page.');
        }
        $this->checkUserPermissionForDemande($id, $entityManager);
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        
        $userLdap = $this->security->getUser();
        if ($temporaryData->getUser()->getUid() !== $userLdap->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette demande.');
        }
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $data = $temporaryData->getData();

          /**
     * Récupère les dossiers partagés et les informations du service.
     * Ces données sont utilisées pour configurer le formulaire.
     */
        $dossiersPartages = $data['dossiers_partages'] ?? [];
        $dossiersSelectionnes = []; 
        $nomServiceSelectionne = $data['nom_service_selectionne'] ?? '';
        $nomValideur = $data['nom_valideur'] ?? '';
       
    
        $form = $this->createForm(DemandeEtape3FormType::class, $data, [
            'dossiers_partages' => $dossiersPartages,
            'data_class' => null,
            'dossiers_selectionnes' => $dossiersSelectionnes,
        ]);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isFormulaireComplet($data)) {
                sleep(1);
                $this->addFlash('error', 'Le formulaire est incomplet. Veuillez repasser par toutes les étapes pour compléter les informations manquantes.');
                sleep(1);
                return $this->redirectToRoute('modifier_demandesvalideur_etape1', ['token' => $token]);
            }
            $finalData = $form->getData();
            $choix = $finalData['replace_someone']; 
            $demande->setUidValideur($nomValideur);
            
           
    
            if ($choix === 'oui') { // Si il remplace quelqu'un 
                $demande->setRemplacant(true);
                $demande->setNomRemplacant($finalData['remplacement_nom']);
                $demande->setPrenomRemplacant($finalData['remplacement_prenom']);
                $demande->setTelephoneRemplacant($finalData['telephone_avant_service']);
                $depart = $finalData['parti_rectorat'];
                if ($depart == true) {
                    $demande->setDepart(true);
                    $demande->setAffectationRemplacant('Aucune');
                } else {
                    $demande->setDepart(false);
                    $demande->setAffectationRemplacant($finalData['nouvelle_affectation_service']);
                }
            } else {
                $demande->setRemplacant(false);
                $demande->setNomRemplacant('Non Renseigné.');
                $demande->setPrenomRemplacant('Non Renseigné.');
                $demande->setTelephoneRemplacant('Non Renseigné.');
                $demande->setAffectationRemplacant('Non Renseigné.');
                $demande->setDepart(false);
            }
            $demande->setService($nomServiceSelectionne);
            $historique = new HistoriqueDemande();
            $historique->setDemande($demande);
            $historique->setStatut($demande->getStatuts());
            $historique->setDate(new \DateTime('now', $this->timezone));
            $historique->setStatutOperation('Modification Valideur');
    
            if (!$ressources) {
                $ressources = new Ressources();
            }
            $ressources->setNom('Ressources');
            $ressources->setDemande($demande);
            $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
            if (!empty($dossiersSelectionnes)) {
                $ressources->setContenu(json_encode($dossiersSelectionnes));
            } else {
                $ressources->setContenu('Pas de Ressources disponible pour ce Service.');
            }

            $statut_utilisateur = $finalData['statut'];

            if ($demande->isAutrePersonne()) { 
                $demande->setAutrePersonne(true);
                $demande->setInfosPersonne([
                'nom' => $finalData['nom'],
                'prenom' => $finalData['prenom'],
                'email' => $finalData['email'],
                'date_de_naissance' => $finalData['date_de_naissance'],
                'statut' => $finalData['statut'],
                'fonction' => $finalData['fonction'],
                
                ]);


                $user = $demande->getAutreUtilisateur();
                $fonction = $finalData['fonction'];
                $user->setFonction($fonction);
                if ($statut_utilisateur !== 'Titulaire') {
                    $dateDebutContrat = new \DateTime($finalData['date_debut_contrat']['date']);
                    $user->setDateDebut($dateDebutContrat);
                    $dateFinContrat = new \DateTime($finalData['date_fin_contrat']['date']);
                    $user->setDateFin($dateFinContrat);
                    $user->setStatutPersonne($statut_utilisateur);

                } else {
                    $user->setStatutPersonne($statut_utilisateur);
                    $user->setDateDebut(null);
                    $user->setDateFin(null);
                }


            } else {
                $demande->setAutrePersonne(false);
                $user = $demande->getIDutilisateur();
                $fonction = $finalData['fonction'];
                $user->setFonction($fonction);
                if ($statut_utilisateur !== 'Titulaire') {
                    $dateDebutContrat = new \DateTime($finalData['date_debut_contrat']['date']);
                    $user->setDateDebut($dateDebutContrat);
                    $dateFinContrat = new \DateTime($finalData['date_fin_contrat']['date']);
                    $user->setDateFin($dateFinContrat);
                    $user->setStatutPersonne($statut_utilisateur);
                } else {
                    $user->setStatutPersonne($statut_utilisateur);
                    $user->setDateDebut(null);
                    $user->setDateFin(null);
                }

            }
            $missions = $finalData['missions'];
            $demande->SetMissions($missions);
            $entityManager->persist($demande);
            $entityManager->persist($historique);
            $entityManager->persist($ressources);
            $entityManager->remove($temporaryData);
            $entityManager->flush();
    
            $this->addFlash('success', 'La demande a été modifiée avec succès.');
    
            if ($this->superUserChecker->isSuperUser()) {
                return $this->redirectToRoute('admin_demandes'); 
            } else {
                return $this->redirectToRoute('demandes_a_valider'); 
            }
        }
    
        return $this->render('valideur/modifier_etape3.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'dossiersPartages' => $dossiersPartages,
            'nomServiceSelectionne' => $nomServiceSelectionne,
            'nomValideur' => $nomValideur,
            'demande' => $demande,
            'token' => $token,
        ]);
    }



/**
 * Vérifie si il est bien le valideur attendu par la demande dans le champs uid valideur de la table demande.
 *
 * @param int $demandeId L'identifiant de la demande à vérifier.
 * @param EntityManagerInterface $entityManager L'EntityManager pour interagir avec la base de données.
 *
 * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException Si la demande n'est pas trouvée.
 * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException Si l'utilisateur connecté n'est pas autorisé à accéder à la demande.
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
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette demande..');
        }
    
        // Comparer les emails
        if ($uid_current !== $uid_valideur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette demande.');
        }
    }


    private function isValideurOrSuperUser(): bool
{
    $isSuperUser  =  $this->superUserChecker->isSuperUser();
        if ($isSuperUser) {
            return true;
        }

    // Sinon, vérifier s'il est valideur
    return $this->roleChecker->isUserValideur();
}


   
/**
 * Construit une structure d'arbre à partir d'une liste de services.
 *
 * @param array $services La liste des services sous forme de tableau associatif.
 * @param int $parentId L'identifiant du service parent pour lequel les enfants doivent être trouvés.
 *
 * @return array La structure d'arbre construite avec les services organisés par hiérarchie.
 */
    
    private function buildTree(array &$services, $parentId = 0)
    {
        $branch = [];
        foreach ($services as &$service) {
            if ($service['pere'] == $parentId) {
                $children = $this->buildTree($services, $service['id_service']);
                if ($children) {
                    $service['children'] = $children;
                }
                $branch[] = $service;
                unset($service);
            }
        }
        return $branch;
    }


    /**
 * Transforme une structure d'arbre de services en une liste adaptée à un menu déroulant.
 *
 * @param array $services La structure d'arbre contenant les services.
 * @param int $niveau Le niveau de profondeur dans l'arborescence, utilisé pour gérer les indentations.
 *
 * @return array Une liste plate des services, avec des indentations pour refléter la hiérarchie.
 */
private function transformServicesForDropdown(array $services, $niveau = 0): array
{
    $servicesDropdownData = [];

    foreach ($services as $service) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $niveau);
        $servicesDropdownData[html_entity_decode($indent) . $service['service']] = $service['id_service'];

        if (isset($service['children'])) {
            $servicesDropdownData += $this->transformServicesForDropdown($service['children'], $niveau + 1);
        }
    }

    return $servicesDropdownData;
}

    private function isFormulaireComplet(array $data): bool
{
    // Liste des champs obligatoires
    $requiredFields = [
        'nom',                    
        'prenom',                  
        'email',                   
        'date_de_naissance',     
        'fonction',                
        'statut',                 
        'nom_service_selectionne', 
        'replace_someone',
         'selectedService' ,
         'nom_valideur',
         'dossiers_partages',
         'date_debut_contrat',
         'date_fin_contrat',
         'nouvelle_affectation_service',
         'telephone_avant_service',
         'missions',
         'parti_rectorat',
         'remplacement_nom',
         'remplacement_prenom',



    ];

   
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $data)) {
  
            return false;
        }
    }

    return true;
}
    
}