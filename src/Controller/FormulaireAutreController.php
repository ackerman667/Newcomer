<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\UserAutre;
use App\Entity\Demandes;
use App\Entity\HistoriqueDemande;
use App\Entity\Ressources;
use App\Form\DemandeEtape1FormType;
use App\Form\DemandeEtape2FormType;
use App\Form\DemandeEtape3FormType;
use App\Security\UserInformation;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mime\Email;

class FormulaireAutreController extends AbstractController
{
    private $security;
    private $timezone;

    public function __construct(Security $security)
    {
        $this->security = $security;
        $this->timezone = new \DateTimeZone('America/Guadeloupe');
    }

    #[Route('/formulaireldap/a/etape1', name: 'formulaireldap-etape1')]
    public function etape1PourAutre(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $data = $session->get('form_data', []);

        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $session->set('form_data', $data);

            return $this->redirectToRoute('formulaireldap-etape2');
        }

        return $this->render('formulaireautre/etape1.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
        ]);
    }

    #[Route('/formulaireldap/a/etape2', name: 'formulaireldap-etape2')]
    public function etape2PourAutre(MonApplication $monApplication, Request $request, SessionInterface $session, HttpClientInterface $httpClient, EntityManagerInterface $entityManager): Response
    {
      
        $data = $session->get('form_data', []);

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
            $data = $form->getData();
            $session->set('form_data', $data);
            $selectedServiceId = $form->get('selectedService')->getData();

            foreach ($services as $service) {
                if ($service['id_service'] == $selectedServiceId) {
                    $session->set('nom_service_selectionne', $service['service']);
                    $session->set('dossiers_partages', $service['dossiers_partages'] ?? []);
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

            return $this->redirectToRoute('formulaireldap-etape3');
        }

        return $this->render('formulaireautre/etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'current_step' => 2,
            'total_steps' => 3,
        ]);
    }

    #[Route('/formulaireldap/a/etape3', name: 'formulaireldap-etape3')]
    public function etape3PourAutre(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
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
            $demandeId = $session->get('demande_id');
            $nouvelleDemande = $session->get('nouvelle_demande', false);
            $historique = new HistoriqueDemande();
            $user = $this->security->getUser();
            $userInformation = new UserInformation();
            $infos_user = $userInformation->getUserInformation($user);
            $uid = $infos_user['uid'];
            $user_bdd = $entityManager->getRepository(User::class)->findOneBy([
                'uid' => $uid,
                'provenance' => 'ldap'
            ]);
            
            
            if ($nouvelleDemande) {
  
                $user_infos = new UserAutre();
                $user_infos->setNom($data['nom']);
                $user_infos->setPrenom( $data['prenom']);
                $user_infos->setEmail( $data['email']);
                $user_infos->setDateDeNaissance($data['date_de_naissance']);
                $user_infos->setFonction($data['fonction']);
                $user_infos->setStatutPersonne($data['statut']);
                $statut_pers = ($data['statut']);
                if ($statut_pers !== 'Titulaire') {
                    $date_debut_contrat = $data['date_debut_contrat'];
                    $date_fin_contrat = $data['date_fin_contrat'];
                    $user_infos->setDateDebut($date_debut_contrat);
                    $user_infos->setDateFin($date_fin_contrat);
                    
                } else {
                    
                    $user_infos->setDateDebut(null);
                    $user_infos->setDateFin(null);
                }
                $entityManager->persist($user_infos);
                $demande = new Demandes();
                $token = bin2hex(random_bytes(32));
                $demande->setToken($token);
                $demande->setAutreUtilisateur($user_infos);
                $demande->setTitre('Demande pour une autre personne');
                $historique->setStatut('Création');
                $historique->setStatutOperation('Création');
                $this->addFlash('success', 'Votre demande a été créé.');
            } elseif (!$nouvelleDemande && $demandeId) {
                // Modification d'une demande existante
                $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);
            
                if ($demande) {
                    $user_infos = $demande->getAutreUtilisateur();
                    $user_infos->setNom($data['nom']);
                    $user_infos->setPrenom( $data['prenom']);
                    $user_infos->setEmail( $data['email']);
                    $user_infos->setDateDeNaissance($data['date_de_naissance']);
                    $user_infos->setFonction($data['fonction']);
                    $user_infos->setStatutPersonne($data['statut']);
                    $statut_pers = ($data['statut']);
                    if ($statut_pers !== 'Titulaire') {
                        $date_debut_contrat = $data['date_debut_contrat'];
                        $date_fin_contrat = $data['date_fin_contrat'];
                        $user_infos->setDateDebut($date_debut_contrat);
                        $user_infos->setDateFin($date_fin_contrat);
                        
                    } else {
                        
                        $user_infos->setDateDebut(null);
                        $user_infos->setDateFin(null);
                    }
                    $entityManager->persist($user_infos);
                    $historique->setDemande($demande);
                    $historique->setStatut('Modification');
                    $historique->setDate(new \DateTime('now', $this->timezone));
                    $historique->setStatutOperation('Modification');
                    $this->addFlash('success', 'Votre demande a été modifiée.');

                } else {
                    // Cas où la demande n'est pas trouvée, créer une nouvelle demande
                    $demande = new Demandes();
                    $token = bin2hex(random_bytes(32));
                    $demande->setToken($token);
                    $demande->setAutreUtilisateur($user_infos);
                    
                    $demande->setTitre('Demande pour une autre personne');
                    $historique->setStatut('Création');
                    $historique->setStatutOperation('Création');
                    $this->addFlash('success', 'Votre demande a été créé.');
                }
            } else {
                $user_infos = new UserAutre();
                $user_infos->setNom($data['nom']);
                $user_infos->setPrenom( $data['prenom']);
                $user_infos->setEmail( $data['email']);
                $user_infos->setDateDeNaissance($data['date_de_naissance']);
                $user_infos->setFonction($data['fonction']);
                $user_infos->setStatutPersonne($data['statut']);
                $statut_pers = ($data['statut']);
                if ($statut_pers !== 'Titulaire') {
                    $date_debut_contrat = $data['date_debut_contrat'];
                    $date_fin_contrat = $data['date_fin_contrat'];
                    $user_infos->setDateDebut($date_debut_contrat);
                    $user_infos->setDateFin($date_fin_contrat);
                    
                } else {
                 
                    $user_infos->setDateDebut(null);
                    $user_infos->setDateFin(null);
                }
                $entityManager->persist($user_infos);
                // Cas par défaut où aucune demande n'est détectée, création d'une nouvelle demande
                $demande = new Demandes();
                $token = bin2hex(random_bytes(32));
                $demande->setToken($token);
                $demande->setAutreUtilisateur($user_infos);
 
                $demande->setTitre('Demande pour une autre personne');
                $historique->setStatut('Création');
                $historique->setStatutOperation('Création');
                $this->addFlash('success', 'Votre demande a été créé.');
            }
            
            if ($user_bdd){
                $demande->setIDutilisateur($user_bdd);
            } else {
               
                $nom_utilisateur = $infos_user['sn'];
                $prenom_utilisateur = $infos_user['givenname'];
                $email_utilisateur = $infos_user['mail'];
                $dateString = $infos_user['datenaissance'];
                $uid = $infos_user['uid'];
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
                $demande->setIDutilisateur($user);

                dump($user);

            }

           
           
            $demande->setInfosPersonne([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
                'date_de_naissance' => $data['date_de_naissance'],
                'statut' => $data['statut'],
                'fonction' => $data['fonction'],
                
            ]);
            $demande->setService($nomServiceSelectionne);
            $demande->setStatuts('Brouillons');
            $demande->setUidValideur($nomValideur);
            $demande->setMissions($data['missions']);
            $demande->setDate((new \DateTime('now', $this->timezone)));
            $demande->setHeureSoumission((new \DateTime('now', $this->timezone)));
            $demande->setAutrePersonne(true);
          
            $choix = $data['replace_someone'];
            if ($choix === 'oui') {
                $demande->setRemplacant(true);
                $demande->setNomRemplacant($data['remplacement_nom']);
                $demande->setPrenomRemplacant($data['remplacement_prenom']);
                $demande->setTelephoneRemplacant($data['telephone_avant_service']);
                $depart = $data['parti_rectorat'];
                if ($depart) {
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
    
            // Mise à jour de l'historique
            $historique->setDemande($demande);
            $historique->setDate(new \DateTime('now', $this->timezone));
    
            // Gestion des ressources associées
            $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]) ?? new Ressources();
            $ressources->setNom('Ressources');
            $ressources->setDemande($demande);
            $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
            $ressources->setContenu(!empty($dossiersSelectionnes) ? json_encode($dossiersSelectionnes) : 'Pas de ressources sélectionnées / disponible pour ce Service.');
    
            // Persister les changements dans la base de données
            $entityManager->persist($demande);
            $entityManager->persist($historique);
            $entityManager->persist($ressources);
            
            $entityManager->flush();
    
            // Nettoyage de la session
              $session->remove('form_data');
            $session->remove('demande_id');
            $session->remove('nouvelle_demande');
            $session->remove('dossiers_partages');
            $session->remove('_csrf/https-demande_etape1_form');
            $session->remove('_csrf/https-demande_etape2_form');
            $session->remove('_csrf/https-demande_etape3_form');
            $session->remove('nom_service_selectionne');
            $session->remove('nom_valideur');
    
            // Envoi d'e-mail de notification
            // $email = (new Email())
            //     ->from('noreply@ac-guadeloupe.fr')
            //     ->to($data['email'])
            //     ->subject('Votre demande a été soumise')
            //     ->text('Votre demande pour accéder à un poste de travail a été soumise.')
            //     ->html('
            //         <p>Bonjour ' . $data['nom'] . ' ' . $data['prenom'] . ',</p>
            //         <p>Une demande d\'accès à un poste de travail a été créée pour vous.</p>
            //         <p>Merci de vérifier les informations dans le formulaire associé.</p>
            //         <p>Cordialement,</p>
            //         <p><strong>Votre équipe informatique</strong></p>
            //     ');
    
            // $mailer->send($email);
    
            return $this->redirectToRoute('liste_demandes');
        }
    
        return $this->render('formulaireautre/etape3.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 3,
            'total_steps' => 3,
            'dossiersPartages' => $dossiersPartages,
            'nomServiceSelectionne' => $nomServiceSelectionne,
        ]);
    }
    

    // #[Route('/formulaireldap/a/nouvelle_demande', name: 'nouvelle-demande-ldap')]
    // public function nouvelleDemande(SessionInterface $session, EntityManagerInterface $entityManager): Response
    // {
    //     // Réinitialiser les données de la session pour démarrer une nouvelle demande
    //     $session->remove('form_data');
    //     $session->remove('demande_id');
    
    //     $session->set('nouvelle_demande', true);
        
    
    //     return $this->redirectToRoute('formulaireldap-etape1');
    // }
    

    
    // #[Route('/formulaireldap/a/modifier/{id}', name: 'modifier_demandespourautre')]
    // public function modifierDemandePourAutre(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    // {
    //     $demande = $entityManager->getRepository(Demandes::class)->find($id);
    //     if (!$demande) {
    //         throw $this->createNotFoundException('Demande non trouvée.');
    //     }

    //     $data = $demande->getInfosPersonne();
    //     if (isset($data['date_de_naissance']) && is_array($data['date_de_naissance'])) {
    //         $dateString = $data['date_de_naissance']['date']; // Extraction de la chaîne de date
    //         $dateNaissance = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $dateString);
    //         $data['date_de_naissance'] = $dateNaissance; // Remplacer dans le tableau de données
    //     }
    //     $session->set('form_data', $data);
    //     $session->set('demande_id', $id);

    //     return $this->redirectToRoute('formulaireldap-etape1', ['id' => $id]);
    // }

    // // #[Route('/formulaireldap/a/supprimer/{id}', name: 'supprimer_demandespourautre')]
    // // public function supprimerDemandePourAutre($id, EntityManagerInterface $entityManager): RedirectResponse
    // // {
    // //     $demande = $entityManager->getRepository(Demandes::class)->find($id);
    // //     if (!$demande) {
    // //         throw $this->createNotFoundException('Demande non trouvée.');
    // //     }

    // //     $historiques = $entityManager->getRepository(HistoriqueDemande::class)->findBy(['demande' => $demande]);
    // //     foreach ($historiques as $historique) {
    // //         $entityManager->remove($historique);
    // //     }

    // //     $ressources = $entityManager->getRepository(Ressources::class)->findBy(['demande' => $demande]);
    // //     foreach ($ressources as $ressource) {
    // //         $entityManager->remove($ressource);
    // //     }

    // //     $entityManager->remove($demande);
    // //     $entityManager->flush();

    // //     $this->addFlash('success', 'La demande a été supprimée avec succès.');

    // //     return $this->redirectToRoute('liste_demandes');
    // // }

    // #[Route('/formulaireldap/a/valider/{id}', name: 'valider_demandespourautre')]
    // public function validerDemandePourAutre(MonApplication $monApplication, EntityManagerInterface $entityManager, $id): Response
    // {
    //     $demande = $entityManager->getRepository(Demandes::class)->find($id);
    //     if (!$demande) {
    //         throw $this->createNotFoundException('Demande non trouvée.');
    //     }

    //     $demande->setStatuts('En attente');
    //     $entityManager->persist($demande);

    //     $historique = new HistoriqueDemande();
    //     $historique->setDemande($demande);
    //     $historique->setStatut('Envoyée');
    //     $historique->setDate(new \DateTime('now', $this->timezone));
    //     $historique->setStatutOperation('Envoi de la demande');

    //     $entityManager->persist($historique);
    //     $entityManager->flush();

    //     return $this->redirectToRoute('liste_demandes');
    // }

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
