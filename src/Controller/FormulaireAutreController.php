<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\UserAutre;
use App\Entity\TemporaryData;
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
use Symfony\Bundle\SecurityBundle\Security;
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

    #[Route('/formulaireldap/a/etape1/{token}', name: 'formulaireldap-etape1')]
    public function etape1PourAutre(string $token,MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }
       
        $data = $temporaryData->getData();

        $user_ldap = $this->security->getUser();

        if ($temporaryData->getUser()->getUid() !== $user_ldap->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }

        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $temporaryData->setData($form->getData());
            $entityManager->flush();

           return $this->redirectToRoute('formulaireldap-etape2', ['token' => $token]);
        }

        return $this->render('formulaireautre/etape1.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
            'token' => $token,

        ]);
    }

    #[Route('/formulaireldap/a/etape2/{token}', name: 'formulaireldap-etape2')]
    public function etape2PourAutre(string $token,MonApplication $monApplication, Request $request, SessionInterface $session, HttpClientInterface $httpClient, EntityManagerInterface $entityManager): Response
    {
      
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }
       
        $data = $temporaryData->getData();

        



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
        


        $user_ldap = $this->security->getUser();

        if ($temporaryData->getUser()->getUid() !== $user_ldap->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
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

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updatedData = $form->getData();
            $temporaryData->setData($updatedData);
            $selectedServiceId = $form->get('selectedService')->getData();

            foreach ($services as $service) {
                if ($service['id_service'] == $selectedServiceId) {
                    $updatedData['nom_service_selectionne'] = $service['service'];
                    $updatedData['dossiers_partages'] = $service['dossiers_partages'] ?? [];
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
    
        return $this->redirectToRoute('formulaireldap-etape3', ['token' => $token]);
        }

        return $this->render('formulaireautre/etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'current_step' => 2,
            'total_steps' => 3,
            'token' => $token,
        ]);
    }

    #[Route('/formulaireldap/a/etape3/{token}', name: 'formulaireldap-etape3')]
    public function etape3PourAutre(string $token,MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {

        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }
       
        $data = $temporaryData->getData();

        $user_ldap = $this->security->getUser();

        if ($temporaryData->getUser()->getUid() !== $user_ldap->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ces données.');
        }
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
            $finalData = $form->getData();
            $action = $temporaryData->getAction();
           
            $historique = new HistoriqueDemande();
            $user = $this->security->getUser();
            $userInformation = new UserInformation();
            $infos_user = $userInformation->getUserInformation($user);
            $uid = $infos_user['uid'];
            $user_bdd = $entityManager->getRepository(User::class)->findOneBy([
                'uid' => $uid,
                'provenance' => 'ldap'
            ]);
            
            
            if ($action === 'create') {
  
                $user_infos = new UserAutre();
                $user_infos->setNom($finalData['nom']);
                $user_infos->setPrenom( $finalData['prenom']);
                $user_infos->setEmail( $finalData['email']);

    $dateDeNaissance = new \DateTime($finalData['date_de_naissance']['date']);
    $user_infos->setDateDeNaissance($dateDeNaissance);

                $user_infos->setFonction($finalData['fonction']);
                $user_infos->setStatutPersonne($finalData['statut']);
                $statut_pers = ($finalData['statut']);
                if ($statut_pers !== 'Titulaire') {
                    $dateDebutContrat = new \DateTime($finalData['date_debut_contrat']['date']);
                    $user_infos->setDateDebut($dateDebutContrat);
                    $dateFinContrat = new \DateTime($finalData['date_fin_contrat']['date']);
                    $user_infos->setDateFin($dateFinContrat);
                    
                    
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
            } elseif ($action === 'modifier') {
                // Modification d'une demande existante
                $demandeId = $finalData['demande_id'] ?? null;
                $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);
            
                if ($demande) {
                    $user_infos = $demande->getAutreUtilisateur();
                    $user_infos->setNom($finalData['nom']);
                    $user_infos->setPrenom( $finalData['prenom']);
                    $user_infos->setEmail( $finalData['email']);
                    if (isset($finalData['date_de_naissance'])) {
                        if (is_array($finalData['date_de_naissance']) && isset($finalData['date_de_naissance']['date'])) {
                            $dateDeNaissance = new \DateTime($finalData['date_de_naissance']['date']);
                        } elseif (is_string($finalData['date_de_naissance'])) {
                            $dateDeNaissance = new \DateTime($finalData['date_de_naissance']);
                        } else {
                            $dateDeNaissance = null; // Vous pouvez définir une valeur par défaut ou lever une exception
                        }
                    
                        if ($dateDeNaissance) {
                            $user_infos->setDateDeNaissance($dateDeNaissance);
                        }
                    }
                    
                    // $user_infos->setDateDeNaissance($finalData['date_de_naissance']);
                    $user_infos->setFonction($finalData['fonction']);
                    $user_infos->setStatutPersonne($finalData['statut']);
                    $statut_pers = ($finalData['statut']);
                    if ($statut_pers !== 'Titulaire') {
                        
                        $dateDebutContrat = new \DateTime($finalData['date_debut_contrat']['date']);
                        $user_infos->setDateDebut($dateDebutContrat);
                        $dateFinContrat = new \DateTime($finalData['date_fin_contrat']['date']);
                        $user_infos->setDateFin($dateFinContrat);
                        
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
                $user_infos->setNom($finalData['nom']);
                $user_infos->setPrenom( $finalData['prenom']);
                $user_infos->setEmail( $finalData['email']);
                $user_infos->setDateDeNaissance($finalData['date_de_naissance']);
                $user_infos->setFonction($finalData['fonction']);
                $user_infos->setStatutPersonne($finalData['statut']);
                $statut_pers = ($finalData['statut']);
                if ($statut_pers !== 'Titulaire') {
                    $date_debut_contrat = $finalData['date_debut_contrat'];
                    $date_fin_contrat = $finalData['date_fin_contrat'];
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

                // dump($user);

            }

           
           
            $demande->setInfosPersonne([
                'nom' => $finalData['nom'],
                'prenom' => $finalData['prenom'],
                'email' => $finalData['email'],
                'date_de_naissance' => $finalData['date_de_naissance'],
                'statut' => $finalData['statut'],
                'fonction' => $finalData['fonction'],
                
            ]);
            $demande->setService($nomServiceSelectionne);
            $demande->setStatuts('Brouillons');
            $demande->setUidValideur($nomValideur);
            $demande->setMissions($finalData['missions']);
            $demande->setDate((new \DateTime('now', $this->timezone)));
            $demande->setHeureSoumission((new \DateTime('now', $this->timezone)));
            $demande->setAutrePersonne(true);
          
            $choix = $finalData['replace_someone'];
            if ($choix === 'oui') {
                $demande->setRemplacant(true);
                $demande->setNomRemplacant($finalData['remplacement_nom']);
                $demande->setPrenomRemplacant($finalData['remplacement_prenom']);
                $demande->setTelephoneRemplacant($finalData['telephone_avant_service']);
                $depart = $finalData['parti_rectorat'];
                if ($depart) {
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
    
         
            $historique->setDemande($demande);
            $historique->setDate(new \DateTime('now', $this->timezone));
    
            $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]) ?? new Ressources();
            $ressources->setNom('Ressources');
            $ressources->setDemande($demande);
            $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
            $ressources->setContenu(!empty($dossiersSelectionnes) ? json_encode($dossiersSelectionnes) : 'Pas de ressources sélectionnées / disponible pour ce Service.');
    
            $entityManager->persist($demande);
            $entityManager->persist($historique);
            $entityManager->persist($ressources);
            $entityManager->remove($temporaryData);
        $entityManager->flush();
            
            // $entityManager->flush();
    
            // Nettoyage de la session
    
    
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
            'token' => $token,
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
