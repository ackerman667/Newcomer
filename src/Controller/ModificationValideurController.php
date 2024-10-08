<?php

namespace App\Controller;

use App\Classe\MonApplication;
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
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class ModificationValideurController extends AbstractController

{
    private $timezone;

    public function __construct()
    {
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
    }








#[Route('formulaireldap/modifierdemandes/etape1/{id}', name: 'modifier_demandesvalideur_etape1')]
public function editDemandeEtape1(int $id, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, MonApplication $monApplication): Response
{
    $demande = $entityManager->getRepository(Demandes::class)->find($id);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

    // Préparer les données en fonction du type de demande (pour soi-même ou pour une autre personne)
    if ($demande->isAutrePersonne()) {
        $infos_personne = $demande->getInfosPersonne();
    
        // Décoder les informations JSON si nécessaire
        if (!is_array($infos_personne)) {
            $infos_personne = json_decode($infos_personne, true) ?? [];
        }
    
        // $dateNaissance = null;
    
        // Vérifier si 'date_de_naissance' est bien un tableau contenant une clé 'date'
        if (!empty($infos_personne['date_de_naissance']['date']) && is_string($infos_personne['date_de_naissance']['date'])) {
            $dateNaissance = \DateTime::createFromFormat('Y-m-d H:i:s.u', $infos_personne['date_de_naissance']['date']);
            if (!$dateNaissance) {
                // Si la conversion échoue, affecter null
                $dateNaissance = null;
            }
        }
    
    

        $data = [
            'nom' => $infos_personne['nom'] ?? '',
            'prenom' => $infos_personne['prenom'] ?? '',
            'email' => $infos_personne['email'] ?? '',
            'date_de_naissance' => $dateNaissance,
            'fonction' => $infos_personne['fonction'] ?? '',
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'date_debut_contrat' => $infos_personne['date_debut_contrat'] ?? null,
            'date_fin_contrat' => $infos_personne['date_fin_contrat'] ?? null,
            'statut' => $infos_personne['statut'] ?? '',
            'missions' => $demande->getMissions(),
        ];
        $user_autre= $demande->getAutreUtilisateur();
        $data2 = [
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
        $session->set('form_data', $data);

        return $this->redirectToRoute('modifier_demandesvalideur_etape2', ['id' => $id]);
    }

    return $this->render('valideur/modifier_etape1.html.twig', [
        'form' => $form->createView(),
        'monApplication' => $monApplication,
        'demande' => $demande
    ]);
}

    #[Route('formulaireldap/modifierdemandes/etape2/{id}', name: 'modifier_demandesvalideur_etape2')]
    public function editDemandeEtape2(int $id, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, HttpClientInterface $httpClient, MonApplication $monApplication): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

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

            return $this->redirectToRoute('modifier_demandesvalideur_etape3', ['id' => $id]);
        }

        return $this->render('valideur/modifier_etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'demande' => $demande,
        ]);
    }

    #[Route('formulaireldap/modifierdemandes/etape3/{id}', name: 'modifier_demandesvalideur_etape3')]
    public function editDemandeEtape3(int $id, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, MailerInterface $mailer, MonApplication $monApplication): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
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
            $choix = $data['replace_someone'];
            $demande->setUidValideur($nomValideur);
            
           
    
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

            $statut_utilisateur = $data['statut'];

            if ($demande->isAutrePersonne()) { 
                $demande->setAutrePersonne(true);
                $demande->setInfosPersonne([
                    'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
                'date_de_naissance' => $data['date_de_naissance'],
                'statut' => $data['statut'],
                'fonction' => $data['fonction'],
                
                ]);


                $user = $demande->getAutreUtilisateur();
                $fonction = $data['fonction'];
                $user->setFonction($fonction);
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


            } else {
                $demande->setAutrePersonne(false);
                $user = $demande->getIDutilisateur();
                $fonction = $data['fonction'];
                $user->setFonction($fonction);
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

            }
            $missions = $data['missions'];
            $demande->SetMissions($missions);
            $entityManager->persist($demande);
            $entityManager->persist($historique);
            $entityManager->persist($ressources);
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
            'demande' => $demande
        ]);
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    
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