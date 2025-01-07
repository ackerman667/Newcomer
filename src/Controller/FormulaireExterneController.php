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



    #[Route('/formulaireexterne/etape1/{token}/{uuid}', name: 'formulaireexterne_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, $token, $uuid): Response
    {


        $info = $this->getVerif($entityManager, $uuid, $token);
        // Récupérer l'utilisateur à partir du token
        // try {
        //     $info = $this->getVerif($entityManager, $token, $uuid);
        // } catch (\Exception $e) {
        //     return $this->redirectToRoute('session_expired');
        // }
        
    
        

        // Charger les données stockées (ou initialiser un tableau vide si aucune donnée)
        $data = $info['data'];
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }

        // Créer le formulaire avec les données chargées
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        // Traiter le formulaire lorsqu'il est soumis
        if ($form->isSubmitted() && $form->isValid()) {
            // Mettre à jour les données dans TemporaryData
            $temporaryData->setData($form->getData());
            $entityManager->flush();

            // Rediriger vers l'étape 2
            return $this->redirectToRoute('formulaireexterne_etape2', [
                'token' => $token,
                'uuid' => $uuid,
            ]);
        }

        // Rendre le formulaire pour l'étape 1
        return $this->render('formulaire/etape1.html.twig', [
            'form' => $form->createView(),
            'current_step' => 1,
            'total_steps' => 3,
            'token' => $token,
            'uuid' => $uuid,
            'monApplication' => $monApplication,
        ]);
    }




    #[Route('/formulaireexterne/etape2/{token}/{uuid}', name: 'formulaireexterne_etape2')]
    public function etape2(
        MonApplication $monApplication,
        Request $request,
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        $token,
        $uuid
    ): Response {

        // Récupérer les informations via getVerif
$info = $this->getVerif($entityManager, $uuid, $token);

$user = $info['user'];  // L'utilisateur associé
$data = $info['data'];  // Les données temporaires

$temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);

if (!$temporaryData) {
    throw $this->createNotFoundException('Données temporaires introuvables.');
}
if (!empty($data['date_de_naissance'])) {
    if (is_array($data['date_de_naissance']) && isset($data['date_de_naissance']['date'])) {
        $data['date_de_naissance'] = new \DateTime($data['date_de_naissance']['date']);
    } elseif (is_string($data['date_de_naissance'])) {
        $data['date_de_naissance'] = new \DateTime($data['date_de_naissance']);
    }
}

if (!empty($data['date_debut_contrat'])) {
    if (is_array($data['date_debut_contrat']) && isset($data['date_debut_contrat']['date'])) {
        $data['date_debut_contrat'] = new \DateTime($data['date_debut_contrat']['date']);
    } elseif (is_string($data['date_debut_contrat'])) {
        $data['date_debut_contrat'] = new \DateTime($data['date_debut_contrat']);
    }
}

if (!empty($data['date_fin_contrat'])) {
    if (is_array($data['date_fin_contrat']) && isset($data['date_fin_contrat']['date'])) {
        $data['date_fin_contrat'] = new \DateTime($data['date_fin_contrat']['date']);
    } elseif (is_string($data['date_fin_contrat'])) {
        $data['date_fin_contrat'] = new \DateTime($data['date_fin_contrat']);
    }
}

// Compléter les données utilisateur si elles manquent
$data['nom'] = $data['nom'] ?? $user->getNom();
$data['prenom'] = $data['prenom'] ?? $user->getPrenom();
$data['email'] = $data['email'] ?? $user->getEmail();
$data['date_de_naissance'] = $data['date_de_naissance'] ?? $user->getDateDeNaissance();
$data['fonction'] = $data['fonction'] ?? $user->getFonction();
$data['statut'] = $data['statut'] ?? $user->getStatutPersonne();

if ($user->getStatutPersonne() !== 'Titulaire') {
    $data['date_debut_contrat'] = $data['date_debut_contrat'] ?? $user->getDateDebut();
    $data['date_fin_contrat'] = $data['date_fin_contrat'] ?? $user->getDateFin();
}

        
    
    
        // Appel API pour récupérer les services
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
    
        // Créer le formulaire
        $form = $this->createForm(DemandeEtape2FormType::class, $data, [
            'services' => $servicesDropdownData,
        ]);
    
        $form->handleRequest($request);
    
        // Traiter le formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
    
            // Mettre à jour les données temporaires
            $temporaryData->setData($data);
    
            // Identifier le service sélectionné
            $selectedServiceId = $form->get('selectedService')->getData();
            foreach ($services as $service) {
                if ($service['id_service'] == $selectedServiceId) {
                    $data['nom_service_selectionne'] = $service['service'];
                    $data['dossiers_partages'] = $service['dossiers_partages'] ?? [];
                    break;
                }
            }
    
            // Appel API pour récupérer le valideur
            $apiUrlSecond = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/valideur/' . $selectedServiceId;
            $responseSecond = $httpClient->request('GET', $apiUrlSecond, [
                'headers' => [
                    'x-auth-token' => $apiToken,
                    'Accept' => 'application/json',
                ],
            ]);
    
            $apiDataSecond = $responseSecond->toArray();
            $data['nom_valideur'] = $apiDataSecond[0]['valideur'] ?? null;
    
            // Sauvegarder les données dans TemporaryData
            $temporaryData->setData($data);
            $entityManager->flush();
    
            // Rediriger vers l'étape 3
            return $this->redirectToRoute('formulaireexterne_etape3', [
                'token' => $token,
                'uuid' => $uuid,
            ]);
        }
    
        // Rendre le formulaire pour l'étape 2
        return $this->render('formulaire/etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'current_step' => 2,
            'total_steps' => 3,
            'token' => $token,
            'uuid' => $uuid,
        ]);
    }
    






    #[Route('/formulaireexterne/etape3/{token}/{uuid}', name: 'formulaireexterne_etape3')]
public function etape3(
    MonApplication $monApplication,
    Request $request,
    EntityManagerInterface $entityManager,
    MailerInterface $mailer,
    $token,
    $uuid
): Response {
    // Récupérer l'utilisateur à partir du token
    try {
        $info = $this->getVerif($entityManager, $uuid, $token);
    } catch (\Exception $e) {
        return $this->redirectToRoute('session_expired');
    }

    $user = $info['user'];  // L'utilisateur associé
    $data = $info['data'];  // Les données temporaires
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }

    // Compléter les valeurs par défaut si nécessaire
    $dossiersPartages = $data['dossiers_partages'] ?? [];
    $nomServiceSelectionne = $data['nom_service_selectionne'] ?? '';
    $nomValideur = $data['nom_valideur'] ?? '';

    // Créer le formulaire avec les données chargées
    $form = $this->createForm(DemandeEtape3FormType::class, $data, [
        'dossiers_partages' => $dossiersPartages,
        'data_class' => null, 
    ]);
    $form->handleRequest($request);

    // Traiter le formulaire lorsqu'il est soumis
    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->getData();
        $historique = new HistoriqueDemande();
        $action = $temporaryData->getAction();

        if ($action === 'create' ) {
           
            $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);
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
} else {

    $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
    if (!$demande) {
                    $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);
             
                    
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
                    $id_user = $demande->getIDutilisateur();
                    $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
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







   $choix = $data['replace_someone'];
   $statut_utilisateur = $data['statut'];
   $nom = $data['nom'];
   $prenom = $data['prenom'];
   $fonction = $data['fonction'];
   $missions = $data['missions'];
   $dateDeNaissance = new \DateTime($data['date_de_naissance']['date']);
    $user->setDateDeNaissance($dateDeNaissance);
   $user->setNom($nom);
   $user->setPrenom($prenom);
   $user->setFonction($fonction);
   $demande->setMissions($missions);
   $user->setFonction($fonction);
//    $user->setDateDeNaissance($datedenaissance);
  
   if ($choix === 'oui') {
       $demande->setRemplacant(true);
       $demande->setNomRemplacant($data['remplacement_nom']);
       $demande->setPrenomRemplacant($data['remplacement_prenom']);
       $demande->setTelephoneRemplacant($data['telephone_avant_service']);
       $depart = $data['parti_rectorat'];
       if ($depart == true) {
           $demande->setDepart(true);
           $demande->setAffectationRemplacant('Aucune');
       } else {
           $demande->setDepart(false);
           $demande->setAffectationRemplacant($data['nouvelle_affectation_service']);
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
    $dateDebutContrat = new \DateTime($data['date_debut_contrat']['date']);
    $dateFinContrat = new \DateTime($data['date_fin_contrat']['date']);
       $user->setDateDebut($dateDebutContrat);
       $user->setDateFin($dateFinContrat);
       $user->setStatutPersonne($statut_utilisateur);
   } else {
       $user->setStatutPersonne($statut_utilisateur);
       $user->setDateDebut(null);
       $user->setDateFin(null);
   }

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
        $url = $this->generateUrl('demande_externe', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $email = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($user->getEmail())
            ->subject('Votre demande a été enregistrée')
            ->html("
                <p>Bonjour {$user->getPrenom()} {$user->getNom()},</p>
                <p>Votre demande d'accès à un poste informatique a bien été enregistrée.</p>
                <p>Pour consulter ou modifier votre demande, veuillez cliquer sur le lien suivant :</p>
                <p><a href='{$url}'>Consulter ma demande</a></p>
                <p>Bien cordialement,<br>L'équipe informatique</p>
            ");
        $mailer->send($email);

        // Supprimer les données temporaires
        $entityManager->remove($temporaryData);
        $entityManager->flush();

        return $this->redirectToRoute('demande_externe', ['token' => $user->getToken()]);
    }

    return $this->render('formulaire/etape3.html.twig', [
        'form' => $form->createView(),
        'monApplication' => $monApplication,
        'dossiersPartages' => $dossiersPartages,
        'current_step' => 3,
        'total_steps' => 3,
        'nomServiceSelectionne' => $nomServiceSelectionne,
        'token' => $token,
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
    
    private function transformServicesForDropdown(array $services, $niveau = 0): array
    {
        if ($niveau == 0) {
            $servicesDropdownData = ['...' => ''];
        } else {
            $servicesDropdownData = [];
        }
        foreach ($services as $service) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $niveau);
            $servicesDropdownData[html_entity_decode($indent) . $service['service']] = $service['id_service'];
            if (isset($service['children'])) {
                $servicesDropdownData += $this->transformServicesForDropdown($service['children'], $niveau + 1);
            }
        }
        return $servicesDropdownData;
    }

    private function getVerif(EntityManagerInterface $entityManager, string $uuid, string $token): ?array
{
    // Récupérer la ligne de TemporaryData en fonction de l'UUID
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);

    if (!$temporaryData) {
        throw $this->createNotFoundException('Données temporaires introuvables.');
    }

    // Vérifier l'action (create ou modifier)
    $action = $temporaryData->getAction();

    if ($action === 'create') {
        // Si l'action est "create", récupérer l'utilisateur via le token
        $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        return [
            'user' => $user,
            'action' => 'create',
            'data' => $temporaryData->getData(),
        ];
    } elseif ($action === 'modifier') {
        // Si l'action est "modifier", récupérer la demande via le token
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $user = $demande->getIDutilisateur();

        return [
            'user' => $user,
            'action' => 'modifier',
            'data' => $temporaryData->getData(),
            'demande' => $demande,
        ];
    }

    throw new \LogicException('Action non valide dans TemporaryData.');
}








    
}
