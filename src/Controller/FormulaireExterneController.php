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



    #[Route('/formulaireext/etape1/{uuid}', name: 'formulaireexterne_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager,  $uuid): Response
    {
        if ($temporaryData->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
            
        }
        $user = $this->getUser();
        


        // Récupérer l'utilisateur à partir du token
        // try {
        //     $info = $this->getVerif($entityManager, $token, $uuid);
        // } catch (\Exception $e) {
        //     return $this->redirectToRoute('session_expired');
        // }
        
    
        $data = $temporaryData->getData();


      

     
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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




    #[Route('/formulaireext/etape2/{uuid}', name: 'formulaireexterne_etape2')]
    public function etape2( MonApplication $monApplication,Request $request, HttpClientInterface $httpClient, EntityManagerInterface $entityManager, $uuid
    ): Response {
        if ($temporaryData->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }

        // Récupérer les informations via getVerif
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
            
        }
        $user = $this->getUser();
        // if ($temporaryData->getUser() !== $this->getUser()) {
        //     throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        // }
        // if ($temporaryData->getUser()->getUid() !== $user->getUid()) {
        //     throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        // }



        $tmp = $temporaryData->getData();
        $date_naissance = $user->getDateDeNaissance();


      
        // $date = \DateTimeImmutable::createFromFormat('d/m/Y', $date_naissance);
        $data = array_merge($tmp, [
            'nom' => !empty($tmp['nom']) ? $tmp['nom'] : ($user->getNom() ?? ''),

            'prenom' => !empty($tmp['prenom']) ? $tmp['prenom'] : ($user->getPrenom() ?? ''),

             'email' => $user->getEmail(),

             'date_de_naissance' => isset($tmp['date_de_naissance']) && is_string($tmp['date_de_naissance'])
             ? new \DateTime($tmp['date_de_naissance'])
             : ($user->getDateDeNaissance() ?? null),

             'fonction' => !empty($tmp['fonction']) ? $tmp['fonction'] : ($user->getFonction() ?? ''),

             'statut' => !empty($tmp['statut']) ? $tmp['statut'] : ($user->getStatutPersonne() ?? ''),

             'date_debut_contrat' => isset($tmp['date_debut_contrat']) && is_string($tmp['date_debut_contrat'])
             ? new \DateTime($tmp['date_debut_contrat'])
             : ($user->getDateDebut() ?? null),

         'date_fin_contrat' => isset($tmp['date_fin_contrat']) && is_string($tmp['date_fin_contrat'])
             ? new \DateTime($tmp['date_fin_contrat'])
             : ($user->getDateFin() ?? null),
     ]);

// if (!empty($data['date_de_naissance'])) {
//     if (is_array($data['date_de_naissance']) && isset($data['date_de_naissance']['date'])) {
//         $data['date_de_naissance'] = new \DateTime($data['date_de_naissance']['date']);
//     } elseif (is_string($data['date_de_naissance'])) {
//         $data['date_de_naissance'] = new \DateTime($data['date_de_naissance']);
//     }
// }

// if (!empty($data['date_debut_contrat'])) {
//     if (is_array($data['date_debut_contrat']) && isset($data['date_debut_contrat']['date'])) {
//         $data['date_debut_contrat'] = new \DateTime($data['date_debut_contrat']['date']);
//     } elseif (is_string($data['date_debut_contrat'])) {
//         $data['date_debut_contrat'] = new \DateTime($data['date_debut_contrat']);
//     }
// }

// if (!empty($data['date_fin_contrat'])) {
//     if (is_array($data['date_fin_contrat']) && isset($data['date_fin_contrat']['date'])) {
//         $data['date_fin_contrat'] = new \DateTime($data['date_fin_contrat']['date']);
//     } elseif (is_string($data['date_fin_contrat'])) {
//         $data['date_fin_contrat'] = new \DateTime($data['date_fin_contrat']);
//     }
// }

// Compléter les données utilisateur si elles manquent
// $data['nom'] = $data['nom'] ?? $user->getNom();
// $data['prenom'] = $data['prenom'] ?? $user->getPrenom();
// $data['email'] = $data['email'] ?? $user->getEmail();
// $data['date_de_naissance'] = $data['date_de_naissance'] ?? $user->getDateDeNaissance();
// $data['fonction'] = $data['fonction'] ?? $user->getFonction();
// $data['statut'] = $data['statut'] ?? $user->getStatutPersonne();

// if ($user->getStatutPersonne() !== 'Titulaire') {
//     $data['date_debut_contrat'] = $data['date_debut_contrat'] ?? $user->getDateDebut();
//     $data['date_fin_contrat'] = $data['date_fin_contrat'] ?? $user->getDateFin();
// }

        
    
    
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
            foreach ($services as $service) {
                if ($service['id_service'] == $selectedServiceId) {
                    $updatedData['nom_service_selectionne'] = $service['service'];
                    $updatedData['dossiers_partages'] = $service['dossiers_partages'] ?? [];
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
    






    #[Route('/formulaireext/etape3/{uuid}', name: 'formulaireexterne_etape3')]
public function etape3(
    MonApplication $monApplication,
    Request $request,
    EntityManagerInterface $entityManager,
    MailerInterface $mailer,
    $uuid
): Response {
    if ($temporaryData->getUser() !== $this->getUser()) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
    }
    
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
            
        }
        $user = $this->getUser();
        // if ($temporaryData->getUser() !== $this->getUser()) {
        //     throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        // }

        $data = $temporaryData->getData();

    $dossiersPartages = $data['dossiers_partages'] ?? [];
    $nomServiceSelectionne = $data['nom_service_selectionne'] ?? '';
    $nomValideur = $data['nom_valideur'] ?? '';

    // Créer le formulaire avec les données chargées
    $form = $this->createForm(DemandeEtape3FormType::class, $data, [
        'dossiers_partages' => $dossiersPartages,
        'data_class' => null, 
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
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
       $demande->setNomRemplacant('Pas de remplacant.');
       $demande->setPrenomRemplacant('Pas de remplacant.');
       $demande->setTelephoneRemplacant('Pas de remplacant.');
       $demande->setAffectationRemplacant('Pas de remplacant.');
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

//     private function getVerif(EntityManagerInterface $entityManager, string $uuid): ?array
// {
//     // Récupérer la ligne de TemporaryData en fonction de l'UUID
//     $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $uuid]);

//     if (!$temporaryData) {
//         throw $this->createNotFoundException('Données temporaires introuvables.');
//     }

//     // Vérifier l'action (create ou modifier)
//     $action = $temporaryData->getAction();

//     if ($action === 'create') {
//         // Si l'action est "create", récupérer l'utilisateur via le token
//         $user = $this->getUser();

//         if (!$user) {
//             throw $this->createNotFoundException('Utilisateur introuvable.');
//         }

//         return [
//             'user' => $user,
//             'action' => 'create',
//             'data' => $temporaryData->getData(),
//         ];
//     } elseif ($action === 'modifier') {
//         // Si l'action est "modifier", récupérer la demande via le token
//         $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

//         if (!$demande) {
//             throw $this->createNotFoundException('Demande introuvable.');
//         }

//         $user = $demande->getIDutilisateur();

//         return [
//             'user' => $user,
//             'action' => 'modifier',
//             'data' => $temporaryData->getData(),
//             'demande' => $demande,
//         ];
//     }

//     throw new \LogicException('Action non valide dans TemporaryData.');
// }








    
}
