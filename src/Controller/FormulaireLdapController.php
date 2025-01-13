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

class FormulaireLdapController extends AbstractController
{
    private $security;
    private $timezone;

    public function __construct(Security $security)
    {
        $this->security = $security;
        $this->timezone = new \DateTimeZone('America/Guadeloupe');
    }


    #[Route('/formulaireldap/etape1/{token}', name: 'formulaireldap_etape1')]
    public function etape1(string $token,MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }
        $data = $temporaryData->getData();
        $user = $this->security->getUser();

        if ($temporaryData->getUser()->getUid() !== $user->getUid()) {
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
        $sessionData = $session->all();

        
        // dump($sessionData);

        return $this->render('formulaireldap/etape1ldap.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
            'token' => $token,
        ]);
    }



    #[Route('/formulaireldap/etape2/{token}', name: 'formulaireldap_etape2')]
    public function etape2(string $token, MonApplication $monApplication, Request $request, SessionInterface $session, HttpClientInterface $httpClient, EntityManagerInterface $entityManager): Response
    {
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        if (!$temporaryData) {
            throw $this->createNotFoundException('Données temporaires introuvables.');
        }
        $temp = 

        $user = $this->security->getUser();
        if ($temporaryData->getUser()->getUid() !== $user->getUid()) {
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

        $data = array_merge($tmp, [
                    'nom' => $infos_user['sn'],
                    'prenom' => $infos_user['givenname'],
                     'email' => $infos_user['mail'],
                     'date_de_naissance' => $date,
                     'fonction' => !empty($tmp['fonction']) ? $tmp['fonction'] : ($user1->getFonction() ?? ''),
                     'statut' => !empty($tmp['statut']) ? $tmp['statut'] : ($user1->getStatutPersonne() ?? ''),
                     'date_debut_contrat' => isset($tmp['date_debut_contrat']) && is_string($tmp['date_debut_contrat'])
                     ? new \DateTime($tmp['date_debut_contrat'])
                     : ($user1->getDateDebut() ?? null),
                 'date_fin_contrat' => isset($tmp['date_fin_contrat']) && is_string($tmp['date_fin_contrat'])
                     ? new \DateTime($tmp['date_fin_contrat'])
                     : ($user1->getDateFin() ?? null),
             ]);
        // if (!$user1) {

        // $data = array_merge($data, [
        //     'nom' => $infos_user['sn'],
        //     'prenom' => $infos_user['givenname'],
        //      'email' => $infos_user['mail'],
        //      'date_de_naissance' => $date,

            
        // ]); } 
        // elseif($user1) {
        //     $data = array_merge($data, [
        //         'nom' => $infos_user['sn'],
        //         'prenom' => $infos_user['givenname'],
        //          'email' => $infos_user['mail'],
        //          'date_de_naissance' => $date,
        //          'fonction' => $user1->getFonction(),
        //          'statut' => $user1->getStatutPersonne(),
        //          'date_debut_contrat' => $user1->getDateDebut(),
        //          'date_fin_contrat' => $user1->getDateFin(),
        //         ]);
                 


        // }
        
    

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


    #[Route('/formulaireldap/etape3/{token}', name: 'formulaireldap_etape3')]
    public function etape3(string $token,MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);

    if (!$temporaryData) {
        throw $this->createNotFoundException('Données temporaires introuvables.');
    }

    $data = $temporaryData->getData();

        $user = $this->security->getUser();

        if ($temporaryData->getUser()->getUid() !== $user->getUid()) {
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
            $url = $this->generateUrl('liste_demandes', [], UrlGeneratorInterface::ABSOLUTE_URL);

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
    
            return $this->redirectToRoute('liste_demandes');
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
?>
