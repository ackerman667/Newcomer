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

class ValideurListeController extends AbstractController
{
    #[Route('formulaireldap/listedemandes', name: 'listedemandes')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        $uid = $user->getUid();
        $demandes = $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
            ->where('d.uid_valideur = :uid')
            ->setParameter('uid', $uid)
            ->getQuery()
            ->getResult();

        return $this->render('valideur/index.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
        ]);
    }

    #[Route('formulaireldap/validerdemande/{id}', name: 'valider_demande', methods: ['POST'])]
    public function validerDemande(int $id, EntityManagerInterface $entityManager,  MailerInterface $mailer): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $demande->setStatuts('Validé');
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut('Validée');
        $historique->setStatutOperation('Validation');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);

        $entityManager->flush();

        $email = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($demande->getIDutilisateur()->getEmail())
            ->subject('Votre demande a été validée')
            ->html('<p>Votre demande a été validée.</p>');

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

    #[Route('formulaire/demande/visualiser/{id}', name: 'visualiser_demande')]
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
    $servicesDropdownData = $this->transformServicesForDropdown($services);

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

    $form = $this->createForm(DemandeEtape3FormType::class, $data);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->getData();
        
        
        $demande->setNomRemplacant($data['remplacement_nom']);
        $demande->setPrenomRemplacant($data['remplacement_prenom']);
        $demande->setTelephoneRemplacant($data['telephone_avant_service']);
        $demande->setDepart($data['parti_rectorat']);
        $demande->setAffectationRemplacant($data['nouvelle_affectation_service']);
        // $demande->setDateDebut($data['date_debut_contrat']);
        // $demande->setDateFin($data['date_fin_contrat']);
        // $demande->setStatutPersonne($data['statut']);
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut($demande->getStatuts());
        $historique->setDate(new \DateTime());
        $historique->setStatutOperation('Modification Valideur');
        $ressources->setNom('Dossier Partagés');
        $ressources->setDemande($demande);
                if (!empty($dossiersPartages)) {
                    $ressources->setContenu(json_encode($dossiersPartages));
                } else {
                    $ressources->setContenu('Pas de dossier partagés disponible pour ce Service.');
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

private function transformServicesForDropdown(array $services): array
{
    $servicesDropdownData = [];
    foreach ($services as $service) {
        $servicesDropdownData[$service['service']] = $service['id_service'];
    }

    return $servicesDropdownData;
}






    

}
  

