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
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); // Définir la timezone
    }
    #[Route('formulaireldap/listedemandes', name: 'listedemandes')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        $uid = $user->getUid();
        dump($uid);
        // $user = $security->getUser();
        // $uid = $user->getUid();
        $statut = 'Brouillons'; 
        $demandes = $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
            ->where('d.uid_valideur = :uid')
            ->andWhere('d.statuts <> :statut') 
            ->setParameter('uid', $uid)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getResult();

            dump($demandes);

            $userDemandes = [];

            foreach ($demandes as $demande) {
                $userDemandes[] = [
                    'demande' => $demande,
                    'user' => $demande->getIDutilisateur()
                ];
            }
            dump($userDemandes);
        
        return $this->render('valideur/index.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
            'user' => $userDemandes,
        ]);
    }

    #[Route('formulaireldap/validerdemande/{id}', name: 'valider_demande')]
    public function validerDemande(int $id, EntityManagerInterface $entityManager,  MailerInterface $mailer): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $now = new \DateTime('now', $this->timezone);
        $demande->setStatuts('Suivi dans LEKA');
        $demande->setDateValidation($now);
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut('Suivi dans LEKA');
        $historique->setStatutOperation('Envoi de la demande dans LEKA');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);

        $entityManager->flush();

        $email = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($demande->getIDutilisateur()->getEmail())
            ->subject('Votre demande a été validée')
            ->html('<p>Votre demande a été validée.</p>');

        $mailer->send($email);
    //     $leka = (new Email())
    //     ->from('noreply@ac-guadeloupe.fr')
    //     ->to($demande->getIDutilisateur()->getEmail())
    //     ->subject('Votre demande a été validée')
    //     ->html('<p>Votre demande a été validée.</p>');

    // $mailer->send($leka);


        return $this->redirectToRoute('listedemandes');
    }

    #[Route('formulaireldap/refuserdemande/{id}', name: 'refuser_demande')]
    public function refuserDemande(int $id, EntityManagerInterface $entityManager,  MailerInterface $mailer): Response
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

        $email = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($demande->getIDutilisateur()->getEmail())
            ->subject('Votre demande a été refusée')
            ->html('<p>Votre demande a été refusée.</p>');

        $mailer->send($email);


        return $this->redirectToRoute('listedemandes');
    }


    #[Route('formulaireldap/commenterdemande/{id}', name: 'commenter_demande', methods: ['POST'])]
    public function commenterDemande(int $id, Request $request, EntityManagerInterface $entityManager,  MailerInterface $mailer): Response
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
          // Envoyer un email de notification
          $email = (new Email())
          ->from('noreply@ac-guadeloupe.fr')
          ->to($demande->getIDutilisateur()->getEmail())
          ->subject('Votre demande a reçu un commentaire')
          ->html('<p>Votre demande a reçu un commentaire : ' . $commentaire . '</p>');

      $mailer->send($email);

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
        dump($ressources);
        $user = $demande->getIDutilisateur();
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

        // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
        //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        // }

        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
        $user = $demande->getIDutilisateur();

        // Configurer Dompdf selon vos besoins
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        // Récupérer le contenu HTML de votre template
        $html = $this->renderView('consult/pdf.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'ressources' => $ressources,
        ]);

        // Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);

        // (Optionnel) Définir le format du papier et l'orientation
        $dompdf->setPaper('A4', 'portrait');

        // Rendre le PDF
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

        $user = $demande->getIDutilisateur();
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
        ];

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
  

