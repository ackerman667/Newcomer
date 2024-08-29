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

class FormulaireTestController extends AbstractController
{
    private $timezone;

    public function __construct()
    {
        
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); // Définir la timezone
    }

    #[Route('/formulairetest/etape1/{token}', name: 'formulairetest_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, $token): Response
    {
    
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
        if($demande) {
            $id_user = $demande->getIDutilisateur();
        $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
        } else {
            $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        }
        

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé.');
        }

        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $session->set('form_data', $data);

            return $this->redirectToRoute('formulairetest_etape2', ['token' => $token]);
        }

        return $this->render('formulaire/etape1.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
            'token' => $token
        ]);
    }

    #[Route('/formulairetest/etape2/{token}', name: 'formulairetest_etape2')]
    public function etape2(MonApplication $monApplication, Request $request, SessionInterface $session, HttpClientInterface $httpClient, EntityManagerInterface $entityManager, $token): Response
    {
       
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
        if($demande) {
            $id_user = $demande->getIDutilisateur();
        $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
        } else {
            $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        }

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé.');
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

       

        // Organiser les services en une structure arborescente
        $servicesTree = $this->buildTree($services);

        // Transform services for dropdown
        $servicesDropdownData = $this->transformServicesForDropdown($servicesTree);

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

            return $this->redirectToRoute('formulairetest_etape3', ['token' => $token]);
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

    #[Route('/formulairetest/etape3/{token}', name: 'formulairetest_etape3')]
public function etape3(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer, $token): Response
{
    $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
        if($demande) {
            $id_user = $demande->getIDutilisateur();
        $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
        } else {
            $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        }
    dump($user);
    $testtoken = $user->getToken();


    if (!$user) {
        throw $this->createNotFoundException('Utilisateur non trouvé.');
    }

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

        // Vérifier si une nouvelle demande doit être créée indépendamment des demandes existantes
        $nouvelleDemande = $session->get('nouvelle_demande', false);
        
        // Créer une nouvelle demande si le flag est défini ou aucune demande existante n'est trouvée
        if ($nouvelleDemande || !$entityManager->getRepository(Demandes::class)->findOneBy(['IDutilisateur' => $user])) {
            dump(" Cas Nouvelle demande Ou  Demande Inexistante");
            $demande = new Demandes();
            $token = bin2hex(random_bytes(32)); // Générer un nouveau token pour la nouvelle demande
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
            // Si le flag nouvelle demande n'est pas défini, continuez avec la demande existante
            $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
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


            dump("cas Demande existante");
            if (!$demande) {
                dump("test");
                // Si aucune demande existante trouvée, toujours créer une nouvelle demande par sécurité
                $demande = new Demandes();
                $token = bin2hex(random_bytes(32)); // Générer un nouveau token pour la nouvelle demande
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
            }
        }

        // Logique partagée pour les deux cas (nouvelle ou modification)
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

        // Réinitialiser le flag de nouvelle demande
        $session->remove('nouvelle_demande');

        $url = $this->generateUrl('statuts_token', ['token' => $testtoken], UrlGeneratorInterface::ABSOLUTE_URL);
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
            <p>Ce lien est valable pour une durée de 24 heures. Si vous n\'avez pas demandé cet accès, veuillez ignorer cet e-mail.</p>
            <p>Bien cordialement,</p>
            <p><strong>Votre équipe informatique</strong></p>
        ');
        $this->addFlash('success', 'Votre formulaire a été soumis. Pensez à le valider si vous n\'avez plus de modifications à y apporter.');
        $mailer->send($email);

        return $this->redirectToRoute('statuts_token', ['token' => $testtoken]);
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


    #[Route('/formulairetest/nouvelle_demande/{token}', name: 'nouvelle_demande')]
    public function nouvelleDemande(SessionInterface $session, $token): Response
    {
        // Réinitialiser les données de la session pour démarrer une nouvelle demande
        $session->remove('form_data');
        $session->remove('demande_id');
        $session->set('nouvelle_demande', true); // Indiquer explicitement qu'une nouvelle demande doit être créée
    
        // Rediriger vers la première étape du formulaire pour une nouvelle demande
        return $this->redirectToRoute('formulairetest_etape1', ['token' => $token]);
    }
    

    
    // #[Route('/formulairetest/nouvelle-demande', name: 'formulairetest_nouvelle_demande')]
    // public function nouvelleDemande(MonApplication $monApplication, EntityManagerInterface $entityManager, SessionInterface $session): Response
    // {
    //     // Récupérer l'utilisateur par son token actuel
    //     $token = $session->get('user_token');
    //     $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);
    
    //     if (!$user) {
    //         throw $this->createNotFoundException('Utilisateur non trouvé.');
    //     }
    
    //     // Vérifier s'il y a une demande existante pour cet utilisateur
    //     $existingDemande = $entityManager->getRepository(Demandes::class)->findOneBy(['IDutilisateur' => $user]);
    
    //     if ($existingDemande) {
    //         // Cloner la demande existante
    //         $newDemande = clone $existingDemande;
    //         $newToken = bin2hex(random_bytes(16)); // Générer un nouveau token unique pour cette demande
    //         $newDemande->setToken($newToken);
    
    //         // Sauvegarder la nouvelle demande
    //         $entityManager->persist($newDemande);
    //         $entityManager->flush();
    
    //         // Rediriger l'utilisateur vers l'étape 1 avec le nouveau token
    //         return $this->redirectToRoute('formulairetest_etape1', ['token' => $newToken]);
    //     } else {
    //         // Créer une nouvelle demande vierge
    //         $newToken = bin2hex(random_bytes(16));
    //         $demande = new Demandes();
    //         $demande->setToken($newToken);
    //         $demande->setIDutilisateur($user);
    
    //         // Sauvegarder la nouvelle demande
    //         $entityManager->persist($demande);
    //         $entityManager->flush();
    
    //         // Rediriger l'utilisateur vers l'étape 1 avec le nouveau token
    //         return $this->redirectToRoute('formulairetest_etape1', ['token' => $newToken]);
    //     }
    // }
    
    


    #[Route('/formulairetest/supprimer/{id}', name: 'formulairetest_supprimer')]
    public function supprimerDemande(Request $request, EntityManagerInterface $entityManager, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        //   $x = $demande.getIDUtilisateur();
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

        return $this->redirectToRoute('statuts_token' , ['token' => $token]); 
    }

    #[Route('/formulairetest/modifier/{id}', name: 'modifier_demandes')]
    public function modifierDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $user = $demande->getIDutilisateur();
        $token = $demande->getToken();

        $data = [
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getEmail(),
            'date_de_naissance' => $user->getDateDeNaissance(),
            'fonction' => $user->getFonction(),
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
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

        $session->set('form_data', $data);
        $session->set('demande_id', $id);

        return $this->redirectToRoute('formulairetest_etape1', ['token' => $token]);
    }

    #[Route('/login', name: 'login')]
    public function requestLoginLink(MonApplication $monApplication, LoginLinkHandlerInterface $loginLinkHandler, UserRepository $userRepository, Request $request, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                $loginLinkDetails = $loginLinkHandler->createLoginLink($user);
                $loginLink = $loginLinkDetails->getUrl();

                $email = (new Email())
                    ->from('noreply@ac-guadeloupe.fr')
                    ->to($email)
                    ->subject('Votre lien de connexion')
                    ->cc('Nicolas.Barbeu@ac-guadeloupe.fr')
                    ->text('Voici votre lien de connexion :')
                    ->html('<p>Voici votre lien de connexion :</p><p><a href="' . $loginLink . '">Cliquez ici pour vous connecter</a></p>');

                $mailer->send($email);

                return $this->render('security/lien.html.twig', [
                    'monApplication' => $monApplication,
                ]);
            }
        }

        return $this->render('security/demande_connexion.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
    #[Route('/formulairetest/valider/{id}', name: 'valider_demandes')]
    public function validerDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $id_user = $demande->getIDutilisateur();
        $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id_user]);
        $token= $user->getToken();
        

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
       

        return $this->redirectToRoute('statuts_token', ['token' => $token]);
    }

    #[Route('/login_check', name: 'login_check')]
    public function check(): never
    {
        throw new \LogicException('Ce code ne devrait jamais être atteint');
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
