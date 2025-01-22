<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\Demandes;
use App\Entity\TemporaryData;
use App\Form\DemandeEtape1FormType;
use App\Form\DemandeEtape2FormType;
use App\Form\DemandeEtape3FormType;
use App\Entity\HistoriqueDemande;
use App\Entity\Ressources;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\SecurityBundle\Security;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Security\UserInformation;



/**
 * @brief Contrôleur Symfony pour la gestion d'un formulaire multi-étapes.
 *
 * Ce contrôleur gère la création et la modification des demandes LDAP via un processus en trois étapes :
 * - Étape 1 : Informations sur le remplacement si l'utilisateur remplace quelqu'un.
 * - Étape 2 : Informations personnelles et sélection du service.
 * - Étape 3 : Finalisation de la demande -> Sélection des ressources partagées .
 * si l'utilisateur est passé par la route nouvelle demande => temporary data est vide a la 1ere etape
 * si c'est une modification temporary data contient deja les valeurs de la demande a modifier  
 *
 * @details
 * - Ce processus s'appuie sur des données temporaires (`TemporaryData`) stockées en base de données.
 * - Chaque étape est validée et sauvegardée avant de passer à la suivante.
 * - L'accès est sécurisé par le token de temporary data 
 */

class FormulaireLdapController extends AbstractController
{
    private $security;
    private $timezone;

    public function __construct(Security $security)
    {
        $this->security = $security;
        $this->timezone = new \DateTimeZone('America/Guadeloupe');
    }



    /**
 * @brief Étape 1 : Informations sur le remplacement.
 *
 * @route /formulaireldap/etape1/{token}
 *
 * @param string $token Le token de la demande temporaire.
 * @param MonApplication $monApplication Instance de la classe d'application personnalisée.
 * @param Request $request Requête HTTP.

 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 *
 * @return Response Vue de l'étape 1.
 *
 * @details
 * - Affiche un formulaire pour collecter les informations de remplacement (nom, prénom, etc.).
 * - Valide et sauvegarde les données dans la table `TemporaryData`.
 * - Redirige vers l'étape 2 si le formulaire est valide.
 *
 * ```
 */


    #[Route('/formulaireldap/etape1/{token}', name: 'formulaireldap_etape1')]
    public function etape1(string $token,MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager): Response
    {
         /**
     * Récupère les données temporaires associées à l'utilisateur via le UUID.
     * Si elles n'existent pas, une exception est levée.
     */
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }
        $data = $temporaryData->getData();
        $user = $this->security->getUser();

          /**
     * Récupère l'utilisateur actuellement connecté.
     * Vérifie que les données temporaires appartiennent bien à cet utilisateur.
     */ if ($temporaryData->getUser()->getUid() !== $user->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }
         
       
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $temporaryData->setData($form->getData());
            $entityManager->flush();
          

            return $this->redirectToRoute('formulaireldap_etape2', ['token' => $token]);
        }
        

        
        // dump($sessionData);

        return $this->render('formulaireldap/etape1ldap.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
            'token' => $token,
        ]);
    }


/**
 * @brief Étape 2 : Collecte des informations personnelles et sélection du service.
 *
 * @route /formulaireldap/etape2/{token}
 *
 * @param string $token Token unique associé à la demande temporaire.
 * @param MonApplication $monApplication Instance de la classe personnalisée pour gérer des fonctionnalités spécifiques à l'application.
 * @param Request $request Objet représentant la requête HTTP.
 * @param HttpClientInterface $httpClient Client HTTP pour récupérer dynamiquement les données externes, comme les services disponibles.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine pour interagir avec la base de données.
 *
 * @return Response Retourne une vue contenant le formulaire de l'étape 2.
 *
 * @details
 * - **Validation du token** : Le token est utilisé pour sécuriser l'accès aux données temporaires associées à la demande. Si le token est invalide ou expiré, une erreur est levée.
 * - **Récupération des données utilisateur** :
 *   - Les données sont récupérées via une API Ldap pour pré-remplir le formulaire avec des informations de l'utilsiateur ( son nom, prénom et  mail).
 *   - Les données temporaires de la demande sont fusionnées avec les informations récupérées.
 * - **Sélection des services** :
 *   - Les services sont récupérés dynamiquement via une API externe.
 *   - Les services sont hiérarchisés et transformés pour une utilisation dans un menu déroulant.
 *   - Certains services peuvent être désactivés en fonction des configurations globales.
 * - **Validation et sauvegarde** :
 *   - Les données du formulaire sont validées.
 *   - Le service sélectionné est associé à son valideur, récupéré via une autre API.
 *   - Les données mises à jour sont sauvegardées dans `TemporaryData` pour être utilisées à l'étape suivante.
 *
 * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException Si les données temporaires sont introuvables.
 * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException Si l'utilisateur connecté ne correspond pas à l'utilisateur associé à la demande.

 */


    #[Route('/formulaireldap/etape2/{token}', name: 'formulaireldap_etape2')]
    public function etape2(string $token, MonApplication $monApplication, Request $request, HttpClientInterface $httpClient, EntityManagerInterface $entityManager): Response
    {
         /**
     * Récupère les données temporaires associées à l'utilisateur via le UUID.
     * Si elles n'existent pas, une exception est levée.
     */
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }
        $temp = 

        $user = $this->security->getUser();
          /**
     * Récupère l'utilisateur actuellement connecté.
     * Vérifie que les données temporaires appartiennent bien à cet utilisateur.
     */ if ($temporaryData->getUser()->getUid() !== $user->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }
         
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user);
        $uid = $infos_user['uid'];
        $tmp = $temporaryData->getData();
        $dateString = $infos_user['datenaissance'];
          $date = \DateTimeImmutable::createFromFormat('d/m/Y', $dateString);
          $user1 = $entityManager->getRepository(User::class)->findOneBy([
            'uid' => $uid,
            'provenance' => 'ldap'
        ]);

      // Fusion des données temporaires avec les informations utilisateur récupérées
$data = array_merge($tmp, [
    // Nom de l'utilisateur : récupéré depuis les informations LDAP de l'utilisateur connecté.
    'nom' => $infos_user['sn'],
    
    // Prénom de l'utilisateur : récupéré depuis les informations LDAP de l'utilisateur connecté.
    'prenom' => $infos_user['givenname'],
    
    // Email de l'utilisateur : récupéré depuis les informations LDAP de l'utilisateur connecté.
    'email' => $infos_user['mail'],
    
    // Date de naissance : formatée et convertie en objet DateTimeImmutable depuis les données LDAP.
    'date_de_naissance' => $date,
    
    // Fonction : vérifie si une fonction existe déjà dans les données temporaires 
    // Si aucune fonction n'existe dans les données temporaires, récupère la fonction depuis l'utilisateur en base.
    // Si aucune fonction n'est trouvée en base non plus, la valeur par défaut est une chaîne vide.
    'fonction' => !empty($tmp['fonction']) ? $tmp['fonction'] : ($user1->getFonction() ?? ''),
    
    // Statut : fonctionne de manière similaire à "fonction", vérifie d'abord les données temporaires,
    // puis cherche dans les données en base, et utilise une chaîne vide comme fallback.
    'statut' => !empty($tmp['statut']) ? $tmp['statut'] : ($user1->getStatutPersonne() ?? ''),
    
    // Date de début du contrat :
    // Si une date existe dans les données temporaires (et qu'elle est au format chaîne), elle est convertie en objet DateTime.
    // Sinon, la date de début du contrat est récupérée depuis l'utilisateur en base (si disponible).
    // Si aucune date n'est trouvée, la valeur par défaut est "null".
    'date_debut_contrat' => isset($tmp['date_debut_contrat']) && is_string($tmp['date_debut_contrat'])
        ? new \DateTime($tmp['date_debut_contrat'])
        : ($user1->getDateDebut() ?? null),
    
    // Date de fin du contrat : logique similaire à la date de début.
    'date_fin_contrat' => isset($tmp['date_fin_contrat']) && is_string($tmp['date_fin_contrat'])
        ? new \DateTime($tmp['date_fin_contrat'])
        : ($user1->getDateFin() ?? null),
]);

        
        
    

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

        $apiUrlSecond = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/valideur/' . $selectedServiceId;
        $responseSecond = $httpClient->request('GET', $apiUrlSecond, [
            'headers' => [
                'x-auth-token' => $apiToken,  
                'Accept' => 'application/json',
            ],
        ]);

        $apiDataSecond = $responseSecond->toArray();
        $updatedData['nom_valideur'] = $apiDataSecond[0]['valideur'] ?? null;

        // Sauvegarder les données mises à jour
        $temporaryData->setData($updatedData);
        $entityManager->flush();
    
            
        

           


        return $this->redirectToRoute('formulaireldap_etape3', ['token' => $token]);
        }

        return $this->render('formulaireldap/etape2ldap.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'current_step' => 2,
            'total_steps' => 3,
            'token' => $token,
        ]);
    }


    /**
 * @brief Étape 3 : Finalisation de la demande et soumission.
 *
 * @route /formulaireldap/etape3/{token}
 *
 * @param string $token Token unique associé à la demande temporaire.
 * @param MonApplication $monApplication Instance de la classe personnalisée pour gérer des fonctionnalités spécifiques à l'application.
 * @param Request $request Objet représentant la requête HTTP.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine pour interagir avec la base de données.
 * @param MailerInterface $mailer Service d'envoi d'e-mails pour envoyer des notifications.
 *
 * @return Response Retourne une vue contenant le formulaire de l'étape 3 ou une redirection après soumission.
 *
 * @details
 * - **Validation et récupération des données** :
 *   - Les données de l'étape précédente sont récupérées et validées.
 *   - En fonction du service sélectionné à l'étape 2 on lui affiche la liste des ressources qu'il peut sélectionner
 * - **Traitement de l'action** :
 *   - **Création** : Si l'action est "create", une nouvelle demande est créée avec les informations fournies.
 *   - **Modification** : Si l'action est "modifier", une demande existante est mise à jour.
 *   - **Fallback** : Si aucune action n'est définie, une nouvelle demande est créée par défaut.
 * - **Ajout des ressources** :
 *   - Les dossiers partagés ou autres ressources liées au service sélectionné sont associés à la demande.
 *   - Si aucun dossier n'est sélectionné, un message par défaut est enregistré.
 * - **Mise à jour des informations utilisateur** :
 *   - Les données utilisateur (statut, fonction, dates de contrat) sont mises à jour.
 *   - Si l'utilisateur est un remplaçant, des informations spécifiques sont également sauvegardées.
 * - **Sauvegarde finale** :
 *   - Les entités `Demandes`, `HistoriqueDemande`, `User`, et `Ressources` sont persistées en base de données.
 * - **Nettoyage et redirection** :
 *   - Les données temporaires associées au token sont supprimées après validation.
 *   - Une redirection est effectuée vers une page listant les demandes de l'utilisateur.
 *
 * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException Si les données temporaires sont introuvables.
 * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException Si l'utilisateur connecté ne correspond pas à l'utilisateur associé à la demande.
 *

 * ```
 */



    #[Route('/formulaireldap/etape3/{token}', name: 'formulaireldap_etape3')]
    public function etape3(string $token,MonApplication $monApplication, Request $request,  EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
         /**
     * Récupère les données temporaires associées à l'utilisateur via le UUID.
     * Si elles n'existent pas, une exception est levée.
     */
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);

    if (!$temporaryData) {
        throw $this->createNotFoundException('Données temporaires introuvables.');
    }

    $data = $temporaryData->getData();

        $user = $this->security->getUser();

          /**
     * Récupère l'utilisateur actuellement connecté.
     * Vérifie que les données temporaires appartiennent bien à cet utilisateur.
     */ if ($temporaryData->getUser()->getUid() !== $user->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }
            
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user);
        $nom_utilisateur = $infos_user['sn'];
        $prenom_utilisateur = $infos_user['givenname'];
        $email_utilisateur = $infos_user['mail'];
        $dateString = $infos_user['datenaissance'];
        $uid = $infos_user['uid'];
        $date = \DateTimeImmutable::createFromFormat('d/m/Y', $dateString);
        $datedenaissance_utilisateur = $date;
        $dossiersPartages = $data['dossiers_partages'] ?? [];
        $dossiersSelectionnes = []; 
        $nomServiceSelectionne = $data['nom_service_selectionne'] ?? '';
        $nomValideur = $data['nom_valideur'] ?? '';
       
    
        $user1 = $entityManager->getRepository(User::class)->findOneBy([
            'uid' => $uid,
            'provenance' => 'ldap'
        ]);
    
        if (!$user1) {
            $user1 = new User();
            $user1->setNom($nom_utilisateur);
            $user1->setPrenom($prenom_utilisateur);
            $user1->setDateDeNaissance($datedenaissance_utilisateur);
            $user1->setEmail($email_utilisateur);
            $user1->setCompteActif(true);
            $user1->setUid($uid);
            $user1->setProvenance('ldap');
        }
   
 
        $form = $this->createForm(DemandeEtape3FormType::class, $data, [
            'dossiers_partages' => $dossiersPartages,
            'data_class' => null, 
            'dossiers_selectionnes' => $dossiersSelectionnes,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $finalData = $form->getData();
          
            $historique = new HistoriqueDemande();
            $fonction = $finalData['fonction'];
           
            $action = $temporaryData->getAction();
            
        
            if ($action === 'create') {
                // Création d'une nouvelle demande
                $demande = new Demandes();
                $token = bin2hex(random_bytes(32));
                $demande->setToken($token);
                $historique->setDemande($demande);
                $historique->setStatut($demande->getStatuts());
                $historique->setDate(new \DateTime('now', $this->timezone));
                $historique->setStatut('Création');
                $historique->setStatutOperation('Création');
                $user1->setToken($token);
        
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
                // Modification d'une demande existante
                $demandeId = $finalData['demande_id'] ?? null;
                $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);
        
                if ($demande) {
                    $historique->setDemande($demande);
                    $historique->setStatut($demande->getStatuts());
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
                    $this->addFlash('success', 'Votre demande a été modifiée.');
                } else {
                    // Cas où la demande n'est pas trouvée, créer une nouvelle demande
                    $demande = new Demandes();
                    $token = bin2hex(random_bytes(32));
                    $demande->setToken($token);
                    $historique->setDemande($demande);
                    $historique->setStatut($demande->getStatuts());
                    $historique->setDate(new \DateTime('now', $this->timezone));
                    $historique->setStatut('Création');
                    $historique->setStatutOperation('Création');
                    $user1->setToken($token);
        
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
                }
            } else {
                // Cas par défaut où aucune demande n'est détectée, création d'une nouvelle demande
                $demande = new Demandes();
                $token = bin2hex(random_bytes(32));
                $demande->setToken($token);
                $historique->setDemande($demande);
                $historique->setStatut($demande->getStatuts());
                $historique->setDate(new \DateTime('now', $this->timezone));
                $historique->setStatut('Création');
                $historique->setStatutOperation('Création');
                $user1->setToken($token);
        
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
            }
        
            $choix = $finalData['replace_someone'];
            $statut_utilisateur = $finalData['statut'];
        
    
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
                $demande->setNomRemplacant('Pas de remplacant.');
                $demande->setPrenomRemplacant('Pas de remplacant.');
                $demande->setTelephoneRemplacant('Pas de remplacant.');
                $demande->setAffectationRemplacant('Pas de remplacant.');
                $demande->setDepart(false);
            }
    
            if ($statut_utilisateur !== 'Titulaire') {
                $dateDebutContrat = new \DateTime($finalData['date_debut_contrat']['date']);
                $user1->setDateDebut($dateDebutContrat);
                $dateFinContrat = new \DateTime($finalData['date_fin_contrat']['date']);
                $user1->setDateFin($dateFinContrat);
                $user1->setStatutPersonne($statut_utilisateur);
            } else {
                $user1->setStatutPersonne($statut_utilisateur);
                $user1->setDateDebut(null);
                $user1->setDateFin(null);
            }
            $user1->setFonction($fonction);
            $missions = $finalData['missions'];
    
            $demande->setIDutilisateur($user1);
            $demande->setAutrePersonne(false);
            $demande->setDate((new \DateTime('now', $this->timezone)));
            $demande->setHeureSoumission((new \DateTime('now', $this->timezone)));
            $demande->setTitre('Demande d\'accès à un poste informatique');
            $demande->setStatuts('Brouillons');
            $demande->setUidValideur($nomValideur);
            $demande->setService($nomServiceSelectionne);
            $demande->setMissions($missions);
    
            $entityManager->persist($demande);
            $entityManager->persist($user1);
            $entityManager->persist($historique);
            $entityManager->persist($ressources);
            $entityManager->flush();
            $token1 = $demande->getToken();
            $nom = $user1->getNom();
            $prenom = $user1->getPrenom();
            $url = $this->generateUrl('mes_demandes', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $entityManager->remove($temporaryData);
        $entityManager->flush();
            // $email = (new Email())
            //     ->from('noreply@ac-guadeloupe.fr')
            //     ->to($user1->getEmail())
            //     ->subject('Votre lien de connexion')
            //     ->cc('nbarbeu97180@gmail.com')
            //     ->text('Voici votre lien de connexion :')
            //     ->html('
            //         <p>Bonjour ' . $nom . ' ' . $prenom . ',</p>
            //         <p>Nous avons bien reçu votre demande d\'accès à un poste de travail informatique.</p>
            //         <p>Pour accéder à votre compte, veuillez cliquer sur le lien ci-dessous :</p>
            //         <p><a href="' . $url . '">Cliquez ici pour vous connecter</a></p>

            //         <p>Bien cordialement,</p>
            //         <p><strong>Votre équipe informatique</strong></p>
            //     ');
    
            // $mailer->send($email);
    
            return $this->redirectToRoute('mes_demandes');
        }
    
        return $this->render('formulaireldap/etape3ldap.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 3,
            'nomServiceSelectionne' => $nomServiceSelectionne,
            'total_steps' => 3,
            'dossiersPartages' => $dossiersPartages,
            'token' => $token,
        ]);
    }




 /**
 * Construit une structure hiérarchique d'arbre à partir d'une liste plate de services.
 *
 * @param array $services Liste des services contenant des informations telles que l'ID et le parent.
 * @param int $parentId ID du parent pour lequel construire les branches (par défaut, racine = 0).
 * @return array Arbre hiérarchique des services.
 */
private function buildTree(array &$services, $parentId = 0) {
    $branch = []; // Contiendra les branches de l'arbre pour le parent donné.

    foreach ($services as &$service) {
        // Vérifie si le service actuel est un enfant du parentId
        if ($service['pere'] == $parentId) {
            // Appelle récursivement buildTree pour trouver les enfants de ce service
            $children = $this->buildTree($services, $service['id_service']);

            // Si des enfants sont trouvés, ajoute-les au service actuel
            if ($children) {
                $service['children'] = $children;
            }

            // Ajoute le service actuel  à la branche courante
            $branch[] = $service;

            // Supprime ce service de la liste des services pour éviter des doublons ou des itérations inutiles
            unset($service);
        }
    }

    return $branch; // Retourne l'ensemble des branches pour ce parent.
}

    
  /**
 * Transforme une structure d'arbre de services en un format adapté à un menu déroulant.
 *
 * @param array $services Arbre hiérarchique des services.
 * @param int $niveau Niveau actuel de profondeur dans l'arbre (utilisé pour l'indentation).
 * @return array Liste des services formatée pour un menu déroulant, avec indentation.
 */
private function transformServicesForDropdown(array $services, $niveau = 0): array
{
    // Initialise le tableau pour le menu déroulant
    $servicesDropdownData = ($niveau == 0) ? ['...' => ''] : [];

    foreach ($services as $service) {
        // Ajoute un indent visuel basé sur le niveau de profondeur
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $niveau);
        
        // Ajoute le service au menu déroulant avec son ID en valeur
        $servicesDropdownData[html_entity_decode($indent) . $service['service']] = $service['id_service'];

        // Si le service a des enfants, les traiter récursivement
        if (isset($service['children'])) {
            $servicesDropdownData += $this->transformServicesForDropdown($service['children'], $niveau + 1);
        }
    }

    return $servicesDropdownData; // Retourne le tableau formaté pour le menu déroulant.
}


    
}
?>
