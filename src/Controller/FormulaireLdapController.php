<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\Demandes;
use App\Form\DemandeEtape1FormType;
use App\Form\DemandeEtape2FormType;
use App\Form\DemandeEtape3FormType;
use App\Entity\HistoriqueDemande;
use App\Entity\Ressources;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Dompdf\Dompdf;
use Symfony\Component\Security\Core\Security;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Form\DemandeFormType;
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


    #[Route('/formulaireldap/etape1', name: 'formulaireldap_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $data = $session->get('form_data', []);
       
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $session->set('form_data', $data);
          

            return $this->redirectToRoute('formulaireldap_etape2');
        }

        return $this->render('formulaireldap/etape1ldap.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
        ]);
    }



    #[Route('/formulaireldap/etape2', name: 'formulaireldap_etape2')]
    public function etape2(MonApplication $monApplication, Request $request, SessionInterface $session, HttpClientInterface $httpClient, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user);
        $data = $session->get('form_data', []);
        $dateString = $infos_user['datenaissance'];
          $date = \DateTimeImmutable::createFromFormat('d/m/Y', $dateString);


        $data = array_merge($data, [
            'nom' => $infos_user['sn'],
            'prenom' => $infos_user['givenname'],
             'email' => $infos_user['mail'],
             'date_de_naissance' => $date,

            
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
            
        

           


            return $this->redirectToRoute('formulaireldap_etape3');
        }

        return $this->render('formulaireldap/etape2ldap.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'current_step' => 2,
            'total_steps' => 3,
        ]);
    }


    #[Route('/formulaireldap/etape3', name: 'formulaireldap_etape3')]
    public function etape3(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $user = $this->security->getUser();
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user);
        $nom_utilisateur = $infos_user['sn'];
        $prenom_utilisateur = $infos_user['givenname'];
        $email_utilisateur = $infos_user['mail'];
        $dateString = $infos_user['datenaissance'];
        $uid = $infos_user['uid'];
        $date = \DateTimeImmutable::createFromFormat('d/m/Y', $dateString);
        $datedenaissance_utilisateur = $date;
        $dossiersPartages = $session->get('dossiers_partages', []);
        $nomServiceSelectionne = $session->get('nom_service_selectionne', '');
        $nomValideur = $session->get('nom_valideur', '');
       
    
        // Rechercher l'utilisateur par UID
        $user1 = $entityManager->getRepository(User::class)->findOneBy(['uid' => $uid]);
    
        if (!$user1) {
            // Si l'utilisateur n'existe pas, créer un nouvel utilisateur
            $user1 = new User();
            $user1->setNom($nom_utilisateur);
            $user1->setPrenom($prenom_utilisateur);
            $user1->setDateDeNaissance($datedenaissance_utilisateur);
            $user1->setEmail($email_utilisateur);
            $user1->setCompteActif(true);
            $user1->setUid($uid);
        }
   
    
        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape3FormType::class, $data, [
            'dossiers_partages' => $dossiersPartages,
            'data_class' => null, 
        ]);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $historique = new HistoriqueDemande();
            $fonction = $data['fonction'];


            $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['IDutilisateur' => $user1]);

            if ($demande) {
                $historique->setDemande($demande);
                $historique->setStatut($demande->getStatuts());
                $historique->setDate((new \DateTime('now', $this->timezone)));
                $historique->setStatutOperation('Modification');
               

                $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
                        if (!$ressources) {
                            $ressources = new Ressources();
                        }
                        $ressources->setNom('Dossier Partagés');
                        $ressources->setDemande($demande);
                        $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
                        if (!empty($dossiersSelectionnes)) {
                            $ressources->setContenu(json_encode($dossiersSelectionnes));
                        } else {
                            $ressources->setContenu('Pas de dossier partagés disponible pour ce Service.');
                        }
    

            } else {
                $demande = new Demandes();
                $token = bin2hex(random_bytes(32));
                $demande->setToken($token);
                $expiration = new \DateTimeImmutable('+24 hours');
                $demande->setTokenExpiration($expiration);
                $historique->setDemande($demande);
                $historique->setStatut($demande->getStatuts());
                $historique->setDate((new \DateTime('now', $this->timezone)));
                $historique->setStatutOperation('Création');
                $user1->setToken($token);
                $user1->setTokenExpiration($expiration);
                $ressources = new Ressources();
                        $ressources->setNom('Dossier Partagés');
                        $ressources->setDemande($demande);
                        $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
                        if (!empty($dossiersSelectionnes)) {
                            $ressources->setContenu(json_encode($dossiersSelectionnes));
                        } else {
                            $ressources->setContenu('Pas de dossier partagés disponible pour ce Service.');
                        }
            }
       
            $choix = $data['replace_someone'];
            $statut_utilisateur = $data['statut'];
    
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
                $user1->setDateDebut($date_debut_contrat);
                $user1->setDateFin($date_fin_contrat);
                $user1->setStatutPersonne($statut_utilisateur);
            } else {
                $user1->setStatutPersonne($statut_utilisateur);
                $user1->setDateDebut(null);
                $user1->setDateFin(null);
            }
            $user1->setFonction($fonction);
    
            $demande->setIDutilisateur($user1);
            $demande->setDate((new \DateTime('now', $this->timezone)));
            $demande->setHeureSoumission((new \DateTime('now', $this->timezone)));
            $demande->setTitre('Demande d\'accès à un poste informatique');
            $demande->setStatuts('En attente');
            $demande->setUidValideur($nomValideur);
            $demande->setService($nomServiceSelectionne);
    
            $entityManager->persist($demande);
            $entityManager->persist($user1);
            $entityManager->persist($historique);
            $entityManager->persist($ressources);
            $entityManager->flush();
            $token1 = $demande->getToken();
            $nom = $user1->getNom();
            $prenom = $user1->getPrenom();
            $url = $this->generateUrl('statuts_token_ldap', ['token' => $token1], UrlGeneratorInterface::ABSOLUTE_URL);
            $session->clear();
    
            $email = (new Email())
                ->from('noreply@ac-guadeloupe.fr')
                ->to($user1->getEmail())
                ->subject('Votre lien de connexion')
                ->cc('nbarbeu97180@gmail.com')
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
    
            $mailer->send($email);
    
            return $this->redirectToRoute('statuts_token_ldap', ['token' => $token1]);
        }
    
        return $this->render('formulaireldap/etape3ldap.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 3,
            'nomServiceSelectionne' => $nomServiceSelectionne,
            'total_steps' => 3,
            'dossiersPartages' => $dossiersPartages,
        ]);
    }


    #[Route('/formulaireldap/supprimer/{id}', name: 'formulaireldap_supprimer')]
    public function supprimerDemande($id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
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
    
        $this->addFlash('success', 'La demande a été supprimée avec succès.');
    
        return $this->redirectToRoute('formulaireldap_etape1');
    }






    #[Route('/formulaireldap/modifier/{id}', name: 'modifier_demandesldap')]
    public function modifierDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $user = $this->security->getUser();
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user);
        $email_utilisateur= $infos_user['mail'];
        $user1 = $entityManager->getRepository(User::class)->findOneBy(['email' => $email_utilisateur]);
        $token = $demande->getToken();

        // Pré-remplir les données pour le formulaire
        $data = [
            'nom' => $user1->getNom(),
            'prenom' => $user1->getPrenom(),
            'email' => $user1->getEmail(),
            'date_de_naissance' => $user1->getDateDeNaissance(),
            'fonction' => $user1->getFonction(),
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'statut' => $user1->getStatutPersonne(),
            'fonction' => $user1->getFonction(),
            'date_debut_contrat' => $user1->getDateDebut(),
            'date_fin_contrat' => $user1->getDateFin(),
        ];

        $session->set('form_data', $data);
        $session->set('demande_id', $id);

        return $this->redirectToRoute('formulaireldap_etape1', ['token' => $token]);
    }


    #[Route('/formulaireldap/valider/{id}', name: 'valider_demandesldap')]
    public function validerDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $demande->setStatuts('Envoyé');
        $entityManager->persist($demande);
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
                $historique->setStatut('Envoyé');
                
                $historique->setDate(new \DateTime('now', $this->timezone));
                $historique->setStatutOperation('Envoie de la demande');

                $entityManager->persist($historique);
        $entityManager->flush();
        $token = $demande->getToken();
    

        return $this->redirectToRoute('statuts_token_ldap', ['token' => $token]);
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

    
}
?>
