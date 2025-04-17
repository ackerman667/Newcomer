<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\Demandes;
use App\Entity\Ressources;
use App\Entity\HistoriqueDemande;
use App\Entity\User;
use App\Entity\TemporaryData;
use App\Form\DemandeEtape1FormType;
use App\Form\DemandeEtape2FormType;
use App\Form\DemandeEtape3FormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FormulaireExterneController extends AbstractController
{
    private $timezone;

    public function __construct()
    {
        
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
    }


/**
     * Étape 1 du formulaire externe.
     *
     * @param MonApplication $monApplication Instance de l'application.
     * @param Request $request Requête HTTP en cours.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @param string $uuid Identifiant unique de la demande temporaire.
     * @return Response La réponse HTTP contenant le formulaire pour l'étape 1.
     *
     * @Route("/formulaireext/etape1/{uuid}", name="formulaireexterne_etape1")
     */
    #[Route('/formulaireext/etape1/{uuid}', name: 'formulaireexterne_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager,  $uuid): Response
    {
       // Récupération des données temporaires
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
            
        }
        $user = $this->getUser();
           /**
     * Récupère l'utilisateur actuellement connecté.
     * Vérifie que les données temporaires appartiennent bien à cet utilisateur.
     */
        if ($temporaryData->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }
        
        
    // Initialisation des données pour le formulaire
        $data = $temporaryData->getData();


      

        // Création du formulaire
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mise à jour des données temporaires
            $data = $form->getData();
        
            $temporaryData->setData($form->getData());
            $entityManager->flush();

        
            return $this->redirectToRoute('formulaireexterne_etape2', [
               
                'uuid' => $uuid,
            ]);
        }

  
        return $this->render('formulaire/etape1.html.twig', [
            'form' => $form->createView(),
            'current_step' => 1,
            'total_steps' => 3,
            'uuid' => $uuid,
            'monApplication' => $monApplication,
        ]);
    }



/**
     * Étape 2 du formulaire externe.
     *
     * @param MonApplication $monApplication Instance de l'application.
     * @param Request $request Requête HTTP en cours.
     * @param HttpClientInterface $httpClient Client HTTP pour les appels API.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités.
     * @param string $uuid Identifiant unique de la demande temporaire.
     * @return Response La réponse HTTP contenant le formulaire pour l'étape 2.
     *
     * @Route("/formulaireext/etape2/{uuid}", name="formulaireexterne_etape2")
     */
    #[Route('/formulaireext/etape2/{uuid}', name: 'formulaireexterne_etape2')]
    public function etape2( MonApplication $monApplication,Request $request, HttpClientInterface $httpClient, EntityManagerInterface $entityManager, $uuid
    ): Response {
       

         // Récupération des données temporaires
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
            
        }
        $user = $this->getUser();
          /**
     * Récupère l'utilisateur actuellement connecté.
     * Vérifie que les données temporaires appartiennent bien à cet utilisateur.
     */
        if ($temporaryData->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }
       


        $tmp = $temporaryData->getData();
        $date_naissance = $user->getDateDeNaissance();


      
        // $date = \DateTimeImmutable::createFromFormat('d/m/Y', $date_naissance);
              // Récupère les données temporaires associées à l'utilisateur.
              $tmp = $temporaryData->getData();

              // Récupère l'utilisateur actuellement connecté.
              $user = $this->getUser();
      
              // Fusionne les données temporaires avec celles de l'utilisateur connecté.
              $data = array_merge($tmp, [
                  /**
                   * Récupère le nom de l'utilisateur.
                   * Si le champ 'nom' existe dans les données temporaires, il est utilisé.
                   * Sinon, le nom de l'utilisateur connecté est utilisé comme valeur par défaut.
                   */
                  'nom' => $tmp['nom'] ?? $user->getNom(),
      
                  /**
                   * Récupère le prénom de l'utilisateur.
                   * Si le champ 'prenom' existe dans les données temporaires, il est utilisé.
                   * Sinon, le prénom de l'utilisateur connecté est utilisé comme valeur par défaut.
                   */
                  'prenom' => $tmp['prenom'] ?? $user->getPrenom(),
      
                  /**
                   * Utilise toujours l'email de l'utilisateur connecté,
                   * car il ne doit pas être modifié.
                   */
                  'email' => $user->getEmail(),
      
                  /**
                   * Récupère la date de naissance.
                   * Si 'date_de_naissance' existe dans les données temporaires, elle est convertie en objet \DateTime.
                   * Sinon, utilise la date de naissance de l'utilisateur connecté.
                   */
                  'date_de_naissance' => isset($tmp['date_de_naissance']) 
    ? (is_array($tmp['date_de_naissance']) && isset($tmp['date_de_naissance']['date'])
        ? new \DateTime($tmp['date_de_naissance']['date']) // Si c'est un tableau avec une clé 'date'
        : new \DateTime($tmp['date_de_naissance']) // Si c'est une chaîne
    )
    : $user->getDateDeNaissance(),

      
                  /**
                   * Récupère la fonction de l'utilisateur.
                   * Si le champ 'fonction' existe dans les données temporaires, il est utilisé.
                   * Sinon, la fonction de l'utilisateur connecté est utilisée.
                   */
                  'fonction' => $tmp['fonction'] ?? $user->getFonction(),
      
                  /**
                   * Récupère le statut de l'utilisateur (e.g., Titulaire, Contractuel).
                   * Si 'statut' existe dans les données temporaires, il est utilisé.
                   * Sinon, le statut de l'utilisateur connecté est utilisé.
                   */
                  'statut' => $tmp['statut'] ?? $user->getStatutPersonne(),
      
                  /**
                   * Récupère la date de début de contrat.
                   * Si 'date_debut_contrat' existe dans les données temporaires, elle est convertie en objet \DateTime.
                   * Sinon, utilise la date de début du contrat de l'utilisateur connecté.
                   */
                  'date_debut_contrat' => isset($tmp['date_debut_contrat']) 
    ? (is_array($tmp['date_debut_contrat']) && isset($tmp['date_debut_contrat']['date'])
        ? new \DateTime($tmp['date_debut_contrat']['date']) // Si c'est un tableau avec une clé 'date'
        : new \DateTime($tmp['date_debut_contrat']) // Si c'est une chaîne
    )
    : $user->getDateDebut(),

'date_fin_contrat' => isset($tmp['date_fin_contrat']) 
    ? (is_array($tmp['date_fin_contrat']) && isset($tmp['date_fin_contrat']['date'])
        ? new \DateTime($tmp['date_fin_contrat']['date']) // Si c'est un tableau avec une clé 'date'
        : new \DateTime($tmp['date_fin_contrat']) // Si c'est une chaîne
    )
    : $user->getDateFin(),

              ]);
      

        
    
    
        // Appel API services
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

        if($temporaryData->getAction() =='create') {
            $servicesDropdownData = $this->transformServicesForDropdown($servicesTree, false);
        } else {
            $servicesDropdownData = $this->transformServicesForDropdown($servicesTree, true);
        }
    
    
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
    
            // Identifier le service sélectionné
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
    
            //  API valideur
            $apiUrlSecond = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/valideur/' . $selectedServiceId;
            $responseSecond = $httpClient->request('GET', $apiUrlSecond, [
                'headers' => [
                    'x-auth-token' => $apiToken,
                    'Accept' => 'application/json',
                ],
            ]);

            $updatedData['id_service'] = $service['id_service'];
            $apiDataSecond = $responseSecond->toArray();
            $updatedData['nom_valideur'] = $apiDataSecond[0]['valideur'] ?? null;
    
            // Sauvegarder les données 
            $temporaryData->setData($updatedData);
            $entityManager->flush();
    
            return $this->redirectToRoute('formulaireexterne_etape3', [
             
                'uuid' => $uuid,
            ]);
        }
    
       
        return $this->render('formulaire/etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'current_step' => 2,
            'total_steps' => 3,
            
            'uuid' => $uuid,
        ]);
    }
    





/**
 * @Route("/formulaireext/etape3/{uuid}", name="formulaireexterne_etape3")
 *
 * Contrôle la troisième étape du formulaire, où l'utilisateur sélectionne des ressources partagées
 * et finalise sa demande. Les données temporaires sont utilisées pour pré-remplir le formulaire.
 *
 * @param MonApplication $monApplication Classe personnalisée pour gérer les paramètres globaux.
 * @param Request $request Requête HTTP contenant les données du formulaire.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine pour la persistance des données.
 * @param MailerInterface $mailer Service d'envoi d'e-mails pour les notifications.
 * @param string $uuid Identifiant unique des données temporaires associées à l'utilisateur.
 *
 * @return Response Rendu de la vue de l'étape 3.
 */
    #[Route('/formulaireext/etape3/{uuid}', name: 'formulaireexterne_etape3')]
public function etape3(
    MonApplication $monApplication,
    Request $request,
    EntityManagerInterface $entityManager,
    MailerInterface $mailer,
    $uuid
): Response {
   
      /**
     * Récupère les données temporaires associées à l'utilisateur via le UUID.
     * Si elles n'existent pas, une exception est levée.
     */
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
            
        }

           /**
     * Récupère l'utilisateur actuellement connecté.
     * Vérifie que les données temporaires appartiennent bien à cet utilisateur.
     */
        $user = $this->getUser();
        if ($temporaryData->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }
      

        $data = $temporaryData->getData();

          /**
     * Récupère les ressources partagés et les informations du service.
     * Ces données sont utilisées pour configurer le formulaire.
     */

        $dossiersPartages = $data['dossiers_partages'] ?? []; 
        $dossiersSelectionnes = []; 
        $service_id = $data['id_service'];
    $nomServiceSelectionne = $data['nom_service_selectionne'] ?? '';
    $nomValideur = $data['nom_valideur'] ?? '';

    // Créer le formulaire avec les données chargées
    $form = $this->createForm(DemandeEtape3FormType::class, $data, [
        'dossiers_partages' => $dossiersPartages,
        'data_class' => null, 
        'dossiers_selectionnes' => $data['test123'] ?? [],
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        if (!$this->isFormulaireComplet($data)) {
            sleep(1);
            $this->addFlash('error', 'Le formulaire est incomplet. Veuillez repasser par toutes les étapes pour compléter les informations manquantes.');
            sleep(1);
            return $this->redirectToRoute('formulaireexterne_etape1', ['uuid' => $uuid]);
        }
        $finalData = $form->getData();
        $historique = new HistoriqueDemande();
        $action = $temporaryData->getAction();
        $user = $this->getUser();

        if ($action === 'create' ) {
           
            
            $demande = new Demandes();
            $token = bin2hex(random_bytes(32)); 
            $demande->setToken($token);
            $historique->setDemande($demande);
            $historique->setStatut('Création');
            $historique->setDate(new \DateTime('now', $this->timezone));
            $historique->setStatutOperation('Création');
            $ressources = new Ressources();
            $ressources->setNom('Ressources');
            $ressources->setDemande($demande);
            $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
            if (!empty($dossiersSelectionnes)) {
                $ressources->setContenu(json_encode($dossiersSelectionnes));
            } else {
                $ressources->setContenu('Pas de ressources sélectionnées / disponible pour ce Service.');
            }
            $this->addFlash('success', 'Votre demande a été créé.');
        } elseif ($action === 'modifier') {

    $demandeId = $finalData['demande_id'] ?? null;
                $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);
        
    if (!$demande) {
                    
             
                    
                    $demande = new Demandes();
                    $token = bin2hex(random_bytes(32)); 
                    $demande->setToken($token);
                    $historique->setDemande($demande);
                    $historique->setStatut('Création');
                    $historique->setDate(new \DateTime('now', $this->timezone));
                    $historique->setStatutOperation('Création');

                    $ressources = new Ressources();
                    $ressources->setNom('Ressources');
                    $ressources->setDemande($demande);
                    $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
                    if (!empty($dossiersSelectionnes)) {
                        $ressources->setContenu(json_encode($dossiersSelectionnes));
                    } else {
                        $ressources->setContenu('Pas de ressources sélectionnées / disponible pour ce Service.');
                    }
                    $this->addFlash('success', 'Votre demande a été créé.');
     } elseif($demande) {
                 
                    $historique->setDemande($demande);
                    $historique->setStatut('Modification');
                    $historique->setDate(new \DateTime('now', $this->timezone));
                    $historique->setStatutOperation('Modification');
                    $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
                    if (!$ressources) {
                        $ressources = new Ressources();
                    }
                    $ressources->setNom('Ressources');
                    $ressources->setDemande($demande);
                    $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
                    if (!empty($dossiersSelectionnes)) {
                        $ressources->setContenu(json_encode($dossiersSelectionnes));
                    } else {
                        $ressources->setContenu('Pas de ressources sélectionnées / disponible pour ce Service.');
                    }

                         }   
                         $this->addFlash('success', 'Votre demande a été modifiée.');


   }




   


   $choix = $finalData['replace_someone'];
   $statut_utilisateur = $finalData['statut'];
   $nom = $finalData['nom'];
   $prenom = $finalData['prenom'];
   $fonction = $finalData['fonction'];
   $missions = $finalData['missions'];
   $dateDeNaissance = new \DateTime($finalData['date_de_naissance']['date']);
    $user->setDateDeNaissance($dateDeNaissance);
   $user->setNom($nom);
   $user->setPrenom($prenom);
   $user->setFonction($fonction);
   $demande->setMissions($missions);
   $user->setFonction($fonction);
//    $user->setDateDeNaissance($datedenaissance);
  
   if ($choix === 'oui') {
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

   if ($statut_utilisateur !== 'Titulaire') {
    $dateDebutContrat = new \DateTime($finalData['date_debut_contrat']['date']);
    $dateFinContrat = new \DateTime($finalData['date_fin_contrat']['date']);
       $user->setDateDebut($dateDebutContrat);
       $user->setDateFin($dateFinContrat);
       $user->setStatutPersonne($statut_utilisateur);
   } else {
       $user->setStatutPersonne($statut_utilisateur);
       $user->setDateDebut(null);
       $user->setDateFin(null);
   }
   $demande->setIdService($service_id);
   $demande->setIDutilisateur($user);
   $demande->setAutrePersonne(false);
   $demande->setDate(new \DateTime('now', $this->timezone));
   $demande->setHeureSoumission(new \DateTime('now', $this->timezone));
   $demande->setTitre('Demande d\'accès à un poste informatique');
   $demande->setStatuts('Brouillons');
   $demande->setUidValideur($nomValideur);
   $demande->setService($nomServiceSelectionne);

   $entityManager->persist($user);
   $entityManager->persist($demande);
   $entityManager->persist($historique);
    $entityManager->persist($ressources);
    $entityManager->remove($temporaryData);
        
   $entityManager->flush();

   
      














        // Envoyer l'email de confirmation
        // $url = $this->generateUrl('demande_externe', [], UrlGeneratorInterface::ABSOLUTE_URL);
        // $email = (new Email())
        //     ->from('noreply@ac-guadeloupe.fr')
        //     ->to($user->getEmail())
        //     ->subject('Votre demande a été enregistrée')
        //     ->html("
        //         <p>Bonjour {$user->getPrenom()} {$user->getNom()},</p>
        //         <p>Votre demande d'accès à un poste informatique a bien été enregistrée.</p>
        //         <p>Pour consulter ou modifier votre demande, veuillez cliquer sur le lien suivant :</p>
        //         <p><a href='{$url}'>Consulter ma demande</a></p>
        //         <p>Bien cordialement,<br>L'équipe informatique</p>
        //     ");
        // $mailer->send($email);

        // Supprimer les données temporaires
        $entityManager->remove($temporaryData);
        $entityManager->flush();

        return $this->redirectToRoute('demande_externe');
    }

    return $this->render('formulaire/etape3.html.twig', [
        'form' => $form->createView(),
        'monApplication' => $monApplication,
        'dossiersPartages' => $dossiersPartages,
        'current_step' => 3,
        'total_steps' => 3,
        'nomServiceSelectionne' => $nomServiceSelectionne,
       
        'uuid' => $uuid,
    ]);
}


    

    private function buildTree(array &$services, $parentId = 0) {
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
    
    private function transformServicesForDropdown(array $services, $isModification = false, $niveau = 0): array
    {
        // Inclure les '...' uniquement si ce n'est PAS une modification
        $servicesDropdownData = $isModification ? [] : ['...' => ''];
    
        foreach ($services as $service) {
            // Ajoute un indent visuel basé sur le niveau de profondeur
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $niveau);
    
            // Ajoute le service au menu déroulant avec son ID en valeur
            $servicesDropdownData[html_entity_decode($indent) . $service['service']] = $service['id_service'];
    
            // Si le service a des enfants, les traiter récursivement
            if (isset($service['children'])) {
                $servicesDropdownData += $this->transformServicesForDropdown($service['children'], $isModification, $niveau + 1);
            }
        }
    
        return $servicesDropdownData; // Retourne le tableau formaté pour le menu déroulant.
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
