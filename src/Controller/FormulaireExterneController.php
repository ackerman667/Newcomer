<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\Demandes;
use App\Entity\Ressources;
use App\Entity\HistoriqueDemande;
use App\Entity\User;
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

    #[Route('/formulaireexterne/etape1/{token}', name: 'formulaireexterne_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, $token): Response
    {
    
        // $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
        // if($demande) {
        //     $id_user = $demande->getIDutilisateur();
        // $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
        // } else {
        //     $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        // }
        

        // if (!$user) {
        //     throw $this->createNotFoundException('Utilisateur non trouvé.');
        // }

        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $session->set('form_data', $data);

            return $this->redirectToRoute('formulaireexterne_etape2', ['token' => $token]);
        }

        return $this->render('formulaire/etape1.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
            'token' => $token
        ]);
    }

    #[Route('/formulaireexterne/etape2/{token}', name: 'formulaireexterne_etape2')]
    public function etape2(MonApplication $monApplication, Request $request, SessionInterface $session, HttpClientInterface $httpClient, EntityManagerInterface $entityManager, $token): Response
    {


       
        // $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
        // if($demande) {
        //     $id_user = $demande->getIDutilisateur();
        // $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
        // } else {
        //     $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        // }

        // if (!$user) {
        //     throw $this->createNotFoundException('Utilisateur non trouvé.');
        // }
        $nouvelleDemande = $session->get('nouvelle_demande', false);
        if ($nouvelleDemande)        {
            $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);
                } else {
            $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
                     if($demande) {
                $id_user = $demande->getIDutilisateur();
                $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);

                         } else {
                $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);
                                }
            
                                     }
          

        




        $data = $session->get('form_data', []);
        $data['nom'] = $user->getNom();
        $data['prenom'] = $user->getPrenom();
        $data['email'] = $user->getEmail();
        $data['date_de_naissance'] = $user->getDateDeNaissance();
        $data['fonction'] = $user->getFonction();
        $data['statut'] = $user->getStatutPersonne();

        if($user->getStatutPersonne()!= 'Titulaire') {
            $data['date_debut_contrat'] = $user->getDateDebut();
            $data['date_fin_contrat'] = $user->getDateFin();
        }

        // Appel à l'API pour récupérer les services
        $apiUrl = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/services';
        $apiToken = 'b97b055g210125afb4c5f507dc823958ff18dfa56a12c7n12agch8db58e21767';
        $response = $httpClient->request('GET', $apiUrl, [
            'headers' => [
                'x-auth-token' => $apiToken,
                'Accept' => 'application/json',
            ],
        ]);

        $services = $response->toArray();

        dump($services);

       

        // Organiser les services en une structure arborescente
        $servicesTree = $this->buildTree($services);

        // Transform services for dropdown
        $servicesDropdownData = $this->transformServicesForDropdown($servicesTree);
        dump($servicesDropdownData);

        $form = $this->createForm(DemandeEtape2FormType::class, $data, [
            'services' => $servicesDropdownData,
            
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $session->set('form_data', $data);
            $selectedServiceId = $form->get('selectedService')->getData();

            foreach ($services as $service) {
                if ($service['id_service'] == $selectedServiceId) {
                    $session->set('nom_service_selectionne', $service['service']);
                    if (isset($service['dossiers_partages']) && !empty($service['dossiers_partages'])) {
                        $session->set('dossiers_partages', $service['dossiers_partages']);
                    } else {
                        $session->set('dossiers_partages', []);
                    }
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
       
            $nomValideur = $apiDataSecond[0]['valideur'];
            $session->set('nom_valideur', $nomValideur);

            return $this->redirectToRoute('formulaireexterne_etape3', ['token' => $token]);
        }

        return $this->render('formulaire/etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'current_step' => 2,
            'total_steps' => 3,
            'token' => $token
        ]);
    }

    #[Route('/formulaireexterne/etape3/{token}', name: 'formulaireexterne_etape3')]
public function etape3(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer, $token): Response
{
    

    $data = $session->get('form_data', []);
    $dossiersPartages = $session->get('dossiers_partages', []);
  

    $nomServiceSelectionne = $session->get('nom_service_selectionne', '');
    $nomValideur = $session->get('nom_valideur', '');

    $form = $this->createForm(DemandeEtape3FormType::class, $data, [
        'dossiers_partages' => $dossiersPartages,
        'data_class' => null, 
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->getData();
        $historique = new HistoriqueDemande();


        $nouvelleDemande = $session->get('nouvelle_demande', false);

        if ($nouvelleDemande ) {
           
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
           
          
                   }

        $token_stat=$user->getToken();



        $choix = $data['replace_someone'];
        $statut_utilisateur = $data['statut'];
        $nom = $data['nom'];
        $prenom = $data['prenom'];
        $fonction = $data['fonction'];
        $missions = $data['missions'];
        $datedenaissance = $data['date_de_naissance'];
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setFonction($fonction);
        $demande->setMissions($missions);
        $user->setFonction($fonction);
        $user->setDateDeNaissance($datedenaissance);
       
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
            $date_debut_contrat = $data['date_debut_contrat'];
            $date_fin_contrat = $data['date_fin_contrat'];
            $user->setDateDebut($date_debut_contrat);
            $user->setDateFin($date_fin_contrat);
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
        $entityManager->flush();

        

        $url = $this->generateUrl('demande_externe', ['token' => $token_stat], UrlGeneratorInterface::ABSOLUTE_URL);
        $session->clear();
       

        $email = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($user->getEmail())
            ->subject('Votre lien de connexion')
            ->cc('Nicolas.Barbeu@ac-guadeloupe.fr')
            ->text('Voici votre lien de connexion :')
            ->html('
            <p>Bonjour ' . $nom . ' ' . $prenom . ',</p>
            <p>Nous avons bien reçu votre demande d\'accès à un poste de travail informatique.</p>
            <p>Pour accéder à votre compte, veuillez cliquer sur le lien ci-dessous :</p>
            <p><a href="' . $url . '">Cliquez ici pour vous connecter</a></p>
            <p>Bien cordialement,</p>
            <p><strong>Votre équipe informatique</strong></p>
        ');
        $this->addFlash('success', 'Votre formulaire a été soumis. Pensez à le valider si vous n\'avez plus de modifications à y apporter.');
        $mailer->send($email);

        return $this->redirectToRoute('demande_externe', ['token' => $token_stat]);
    }

    return $this->render('formulaire/etape3.html.twig', [
        'form' => $form->createView(),
        'monApplication' => $monApplication,
        'dossiersPartages' => $dossiersPartages,
        'current_step' => 3,
        'total_steps' => 3,
        'nomServiceSelectionne' => $nomServiceSelectionne,
        'token' => $token
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
    
}
