<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\TemporaryData;
use App\Service\UserRoleChecker;
use App\Entity\Demandes;
use App\Entity\Ressources;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\HistoriqueDemande;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\DemandeEtape1FormType;
use App\Form\DemandeEtape2FormType;
use App\Form\DemandeEtape3FormType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class ModificationValideurController extends AbstractController

{
    private $timezone;
    private $security;
    private $roleChecker;
    public function __construct(Security $security, UserRoleChecker $roleChecker)
    {
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
        $this->security = $security;
        $this->roleChecker = $roleChecker;
        $this->isValideur = $this->roleChecker->isUserValideur();
       
    }





#[Route('formulaireldap/modifierdemandes/etape1/{id}/{token}', name: 'modifier_demandesvalideur_etape1')]
public function editDemandeEtape1(int $id, string $token, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, MonApplication $monApplication): Response
{
    $isValideur = $this->roleChecker->isUserValideur();
    if (!$this->isValideur) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette demande.');
        return $this->redirectToRoute('liste_demandes'); // Remplacez 'homepage' par la route de votre choix
    }


    $demande = $entityManager->getRepository(Demandes::class)->find($id);
    $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
    $userLdap = $this->security->getUser();

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }
    if ($temporaryData->getUser()->getUid() !== $userLdap->getUid()) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette demande.');
    }

    if ($demande->isAutrePersonne()) {
        
        $user_autre= $demande->getAutreUtilisateur();
        $data = [
            'nom' => $user_autre ? $user_autre->getNom() : '',
            'prenom' => $user_autre ? $user_autre->getPrenom() : '',
            'email' => $user_autre ? $user_autre->getEmail() : '',
            'date_de_naissance' => $user_autre ? $user_autre->getDateDeNaissance() : '',
            'fonction' => $user_autre ? $user_autre->getFonction() : '',
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'date_debut_contrat' => $user_autre ? $user_autre->getDateDebut() : null,
            'date_fin_contrat' => $user_autre ? $user_autre->getDateFin() : null,
            'statut' => $user_autre ? $user_autre->getStatutPersonne() : '',
            'missions' => $demande->getMissions(),
        ];
    } else {
        $user = $demande->getIDutilisateur();
        $data = [
            'nom' => $user ? $user->getNom() : '',
            'prenom' => $user ? $user->getPrenom() : '',
            'email' => $user ? $user->getEmail() : '',
            'date_de_naissance' => $user ? $user->getDateDeNaissance() : '',
            'fonction' => $user ? $user->getFonction() : '',
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'date_debut_contrat' => $user ? $user->getDateDebut() : null,
            'date_fin_contrat' => $user ? $user->getDateFin() : null,
            'statut' => $user ? $user->getStatutPersonne() : '',
            'missions' => $demande->getMissions(),
        ];
    }

    $form = $this->createForm(DemandeEtape1FormType::class, $data);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->getData();
        $temporaryData->setData($form->getData());
        $entityManager->flush();

        return $this->redirectToRoute('modifier_demandesvalideur_etape2', [
            'id' => $id,
            'token' => $token,
        ]);
    }

    return $this->render('valideur/modifier_etape1.html.twig', [
        'form' => $form->createView(),
        'monApplication' => $monApplication,
        'demande' => $demande,
        'token' => $token,
        
    ]);
}

    #[Route('formulaireldap/modifierdemandes/etape2/{id}/{token}', name: 'modifier_demandesvalideur_etape2')]
    public function editDemandeEtape2(int $id, string $token, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, HttpClientInterface $httpClient, MonApplication $monApplication): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);

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


        $userLdap = $this->security->getUser();
        if ($temporaryData->getUser()->getUid() !== $userLdap->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette demande.');
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
        $temporaryData->setData($updatedData);
        $entityManager->flush();
    

        return $this->redirectToRoute('modifier_demandesvalideur_etape3', [
            'id' => $id,
            'token' => $token,
        ]);
        }

        return $this->render('valideur/modifier_etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'demande' => $demande,
            'token' => $token,

        ]);
    }

    #[Route('formulaireldap/modifierdemandes/etape3/{id}/{token}', name: 'modifier_demandesvalideur_etape3')]
    public function editDemandeEtape3(int $id, string $token, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, MailerInterface $mailer, MonApplication $monApplication): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
        $temporaryData = $entityManager->getRepository(TemporaryData::class)->findOneBy(['token' => $token]);
        
        $userLdap = $this->security->getUser();
        if ($temporaryData->getUser()->getUid() !== $userLdap->getUid()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette demande.');
        }
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $data = $temporaryData->getData();
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
            $choix = $finalData['replace_someone'];
            $demande->setUidValideur($nomValideur);
            
           
    
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
            $demande->setService($nomServiceSelectionne);
            $historique = new HistoriqueDemande();
            $historique->setDemande($demande);
            $historique->setStatut($demande->getStatuts());
            $historique->setDate(new \DateTime('now', $this->timezone));
            $historique->setStatutOperation('Modification Valideur');
    
            if (!$ressources) {
                $ressources = new Ressources();
            }
            $ressources->setNom('Ressources');
            $ressources->setDemande($demande);
            $dossiersSelectionnes = $form->get('dossiers_partages')->getData();
            if (!empty($dossiersSelectionnes)) {
                $ressources->setContenu(json_encode($dossiersSelectionnes));
            } else {
                $ressources->setContenu('Pas de Ressources disponible pour ce Service.');
            }

            $statut_utilisateur = $finalData['statut'];

            if ($demande->isAutrePersonne()) { 
                $demande->setAutrePersonne(true);
                $demande->setInfosPersonne([
                'nom' => $finalData['nom'],
                'prenom' => $finalData['prenom'],
                'email' => $finalData['email'],
                'date_de_naissance' => $finalData['date_de_naissance'],
                'statut' => $finalData['statut'],
                'fonction' => $finalData['fonction'],
                
                ]);


                $user = $demande->getAutreUtilisateur();
                $fonction = $finalData['fonction'];
                $user->setFonction($fonction);
                if ($statut_utilisateur !== 'Titulaire') {
                    $dateDebutContrat = new \DateTime($finalData['date_debut_contrat']['date']);
                    $user->setDateDebut($dateDebutContrat);
                    $dateFinContrat = new \DateTime($finalData['date_fin_contrat']['date']);
                    $user->setDateFin($dateFinContrat);
                    $user->setStatutPersonne($statut_utilisateur);

                } else {
                    $user->setStatutPersonne($statut_utilisateur);
                    $user->setDateDebut(null);
                    $user->setDateFin(null);
                }


            } else {
                $demande->setAutrePersonne(false);
                $user = $demande->getIDutilisateur();
                $fonction = $finalData['fonction'];
                $user->setFonction($fonction);
                if ($statut_utilisateur !== 'Titulaire') {
                    $dateDebutContrat = new \DateTime($finalData['date_debut_contrat']['date']);
                    $user->setDateDebut($dateDebutContrat);
                    $dateFinContrat = new \DateTime($finalData['date_fin_contrat']['date']);
                    $user->setDateFin($dateFinContrat);
                    $user->setStatutPersonne($statut_utilisateur);
                } else {
                    $user->setStatutPersonne($statut_utilisateur);
                    $user->setDateDebut(null);
                    $user->setDateFin(null);
                }

            }
            $missions = $finalData['missions'];
            $demande->SetMissions($missions);
            $entityManager->persist($demande);
            $entityManager->persist($historique);
            $entityManager->persist($ressources);
            $entityManager->remove($temporaryData);
            $entityManager->flush();
    
            $this->addFlash('success', 'La demande a été modifiée avec succès.');
    
            return $this->redirectToRoute('liste_demandes');
        }
    
        return $this->render('valideur/modifier_etape3.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'dossiersPartages' => $dossiersPartages,
            'nomServiceSelectionne' => $nomServiceSelectionne,
            'nomValideur' => $nomValideur,
            'demande' => $demande,
            'token' => $token,
        ]);
    }

   

    
    private function buildTree(array &$services, $parentId = 0)
    {
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