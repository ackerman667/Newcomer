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
use App\Form\DemandeFormType;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class ValideurListeController extends AbstractController

{
    private $timezone;

    public function __construct()
    {
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
    }
   
    #[Route('formulaireldap/listedemandes', name: 'listedemandes')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        $uid = $user->getUid();
        $statut = 'Brouillons'; 
    
       
        $demandes = $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
            ->where('d.uid_valideur = :uid')
            ->andWhere('d.statuts <> :statut') 
            ->setParameter('uid', $uid)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getResult();
    
        // Préparer les données utilisateur pour chaque demande
        $userDemandes = [];
        foreach ($demandes as $demande) {
            // Si la demande est faite pour une autre personne, utiliser les infos JSON, sinon utiliser les infos de l'utilisateur lié
            if ($demande->isAutrePersonne()) {
                $infos_personne = $demande->getInfosPersonne();
    
                // Si $infos_personne est déjà un tableau, l'utiliser directement, sinon le décoder du JSON
                if (!is_array($infos_personne)) {
                    $infos_personne = json_decode($infos_personne, true) ?? []; // Décoder JSON en tableau
                }
    
                // Préparer les informations de l'utilisateur à partir des données JSON
                $userData = [
                    'nom' => $infos_personne['nom'] ?? '',
                    'prenom' => $infos_personne['prenom'] ?? '',
                    'email' => $infos_personne['email'] ?? '',
                    'date_de_naissance' => $infos_personne['date_de_naissance'] ?? '',
                    'fonction' => $infos_personne['fonction'] ?? '',
                    'statut' => $infos_personne['statut'] ?? ''
                ];
            } else {
                // Si la demande n'est pas pour une autre personne, utiliser les infos de l'utilisateur associé à la demande
                $userEntity = $demande->getIDutilisateur();
                $userData = [
                    'nom' => $userEntity ? $userEntity->getNom() : '',
                    'prenom' => $userEntity ? $userEntity->getPrenom() : '',
                    'email' => $userEntity ? $userEntity->getEmail() : '',
                ];
            }
    
            // Ajouter les informations de la demande et de l'utilisateur à la liste
            $userDemandes[] = [
                'demande' => $demande,
                'user' => $userData,
            ];
        }
        
        return $this->render('valideur/index.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
            'user' => $userDemandes,
        ]);
    }
    
    


    
    #[Route('formulaireldap/validerdemande/{id}', name: 'valider_demande')]
public function validerDemande(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
{
    $demande = $entityManager->getRepository(Demandes::class)->find($id);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

    $now = new \DateTime('now', $this->timezone);
    $demande->setStatuts('Suivi dans LEKA');
    $demande->setDateValidation($now);

    // Créer un historique de la demande
    $historique = new HistoriqueDemande();
    $historique->setDemande($demande);
    $historique->setStatut('Suivi dans LEKA');
    $historique->setStatutOperation('Envoi de la demande dans LEKA');
    $historique->setDate(new \DateTime());
    $entityManager->persist($historique);
    $entityManager->flush();

    // Récupérer l'email de l'utilisateur ou de la personne cible
   // Récupérer les informations de la demande
$infosPersonne = $demande->getInfosPersonne();

// Vérifiez si les informations sont déjà un tableau ou non
if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
    // Décoder le JSON seulement si c'est une chaîne
    $infosPersonne = json_decode($infosPersonne, true);
}

// Récupérez l'email en fonction du type de demande
$email = $demande->isAutrePersonne() ? ($infosPersonne['email'] ?? '') : $demande->getIDutilisateur()->getEmail();

    $emailMessage = (new Email())
        ->from('noreply@ac-guadeloupe.fr')
        ->to($email)
        ->subject('Votre demande a été envoyée dans LEKA')
        ->html('<p>Votre demande a été envoyée dans LEKA.</p>');

    $mailer->send($emailMessage);

    return $this->redirectToRoute('listedemandes');
}


#[Route('formulaireldap/refuserdemande/{id}', name: 'refuser_demande')]
public function refuserDemande(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
{
    $demande = $entityManager->getRepository(Demandes::class)->find($id);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

    $demande->setStatuts('Refusée');
    $historique = new HistoriqueDemande();
    $historique->setDemande($demande);
    $historique->setStatut('Refusée');
    $historique->setStatutOperation('Refus');
    $historique->setDate(new \DateTime());
    $entityManager->persist($historique);
    $entityManager->flush();

    $email = $demande->isAutrePersonne() ? json_decode($demande->getInfosPersonne(), true)['email'] : $demande->getIDutilisateur()->getEmail();

    $emailMessage = (new Email())
        ->from('noreply@ac-guadeloupe.fr')
        ->to($email)
        ->subject('Votre demande a été refusée')
        ->html('<p>Votre demande a été refusée.</p>');

    $mailer->send($emailMessage);

    return $this->redirectToRoute('listedemandes');
}


#[Route('formulaireldap/commenterdemande/{id}', name: 'commenter_demande', methods: ['POST'])]
public function commenterDemande(int $id, Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
{
    $demande = $entityManager->getRepository(Demandes::class)->find($id);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

    $commentaire = $request->request->get('commentaire');
    $demande->setCommentaire($commentaire);
    
    $historique = new HistoriqueDemande();
    $historique->setDemande($demande);
    $historique->setStatut($demande->getStatuts());
    $historique->setStatutOperation('Commentaire');
    $historique->setDate(new \DateTime());
    $entityManager->persist($historique);
    $entityManager->flush();

   // Vérifiez si infos_personne est déjà un tableau ou non
$infosPersonne = $demande->getInfosPersonne();
if ($demande->isAutrePersonne() && is_string($infosPersonne)) {
    // Décoder le JSON seulement si c'est une chaîne
    $infosPersonne = json_decode($infosPersonne, true);
}

// Récupérez l'email en fonction du type de demande
$email = $demande->isAutrePersonne() ? $infosPersonne['email'] ?? '' : $demande->getIDutilisateur()->getEmail();


    $emailMessage = (new Email())
        ->from('noreply@ac-guadeloupe.fr')
        ->to($email)
        ->subject('Votre demande a reçu un commentaire')
        ->html('<p>Votre demande a reçu un commentaire : ' . $commentaire . '</p>');

    $mailer->send($emailMessage);

    return $this->redirectToRoute('listedemandes');
}


#[Route('formulaireldap/demande/visualiser/{id}', name: 'visualiser_demande')]
public function visualiserDemande(MonApplication $monApplication, int $id, EntityManagerInterface $entityManager): Response
{
    $demande = $entityManager->getRepository(Demandes::class)->find($id);

    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

    $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);

    // Vérifier si la demande est pour une autre personne
    if ($demande->isAutrePersonne()) {
        $infos_personne = $demande->getInfosPersonne();

        // Si infos_personne n'est pas déjà un tableau, décoder JSON
        if (!is_array($infos_personne)) {
            $user = json_decode($infos_personne, true);
        } else {
            $user = $infos_personne;
        }
    } else {
        // Sinon, récupérer les informations de l'utilisateur associé à la demande
        $user = $demande->getIDutilisateur();
    }

    return $this->render('valideur/visualiser.html.twig', [
        'demande' => $demande,
        'monApplication' => $monApplication,
        'user' => $user,
        'ressources' => $ressources,
    ]);
}






#[Route('formulaireldap/demandepdf/{id}', name: 'demande_pdf_valideur')]
public function generatePdf($id, EntityManagerInterface $entityManager): Response
{
    $demande = $entityManager->getRepository(Demandes::class)->find($id);

    // Vérifier si la demande est valide
    if (!$demande) {
        throw $this->createNotFoundException('Demande non trouvée.');
    }

    $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);

    // Vérifier si la demande est faite pour une autre personne
    if ($demande->isAutrePersonne()) {
        // Récupérer les informations à partir du JSON
        $infosPersonne = $demande->getInfosPersonne();

        // Vérifier que le contenu est une chaîne JSON avant d'appeler json_decode
        if (is_string($infosPersonne)) {
            $userInfos = json_decode($infosPersonne, true);
        } else {
            // Si ce n'est pas une chaîne, on considère que c'est déjà un tableau
            $userInfos = $infosPersonne;
        }

        if (!empty($userInfos['date_de_naissance']) && is_array($userInfos['date_de_naissance'])) {
            $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d H:i:s.u', $userInfos['date_de_naissance']['date']);
        }
    } else {
        // Récupérer les informations de l'utilisateur lié
        $userInfos = [
            'nom' => $demande->getIDutilisateur()->getNom(),
            'prenom' => $demande->getIDutilisateur()->getPrenom(),
            'email' => $demande->getIDutilisateur()->getEmail(),
            'date_de_naissance' => $demande->getIDutilisateur()->getDateDeNaissance(),
            'fonction' => $demande->getIDutilisateur()->getFonction(),
            'statut' => $demande->getIDutilisateur()->getStatutPersonne(),
            'date_debut' => $demande->getIDutilisateur()->getDateDebut(),
            'date_fin' => $demande->getIDutilisateur()->getDateFin(),
        ];
    }

    // Configurer Dompdf selon vos besoins
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);

    // Récupérer le contenu HTML de votre template
    $html = $this->renderView('consult/pdf.html.twig', [
        'demande' => $demande,
        'user' => $userInfos,
        'ressources' => $ressources,
    ]);

    // Charger le HTML dans Dompdf
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Envoyer le PDF au navigateur
    return new Response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="demande.pdf"',
    ]);
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
        $dateNaissance = null;
        if (!empty($infos_personne['date_de_naissance']) && is_string($infos_personne['date_de_naissance'])) {
            $dateNaissance = \DateTime::createFromFormat('Y-m-d', $infos_personne['date_de_naissance']);
            if (!$dateNaissance) {
                // Si la date n'a pas pu être convertie, affecter null pour éviter une erreur
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

            if ($demande->isAutrePersonne()) { // Corrigez ici pour appeler la méthode correctement
                $demande->setAutrePersonne(true);
            } else {
                $demande->setAutrePersonne(false);
            }
            
            $entityManager->persist($demande);
            $entityManager->persist($historique);
            $entityManager->persist($ressources);
            $entityManager->flush();
    
            $this->addFlash('success', 'La demande a été modifiée avec succès.');
    
            return $this->redirectToRoute('listedemandes');
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
  

