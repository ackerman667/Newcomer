<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\Demandes;
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
    #[Route('/formulairetest/etape1/{token}', name: 'formulairetest_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, $token): Response
    {
        // Retrieve user based on token
        $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

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
        // Retrieve user based on token
        $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé.');
        }

        $data = $session->get('form_data', []);
        $data['nom'] = $user->getNom();
        $data['prenom'] = $user->getPrenom();
        $data['email'] = $user->getEmail();
        $data['date_de_naissance'] = $user->getDateDeNaissance();
        $data['fonction'] = $user->getFonction();

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
        $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé.');
        }

        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape3FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $historique = new HistoriqueDemande();

            // Vérifier si une demande existante doit être mise à jour
            $demandeId = $session->get('demande_id');
            if ($demandeId) {
                $demande = $entityManager->getRepository(Demandes::class)->find($demandeId);
                $historique->setDemande($demande);
                $historique->setStatut($demande->getStatuts());
                $historique->setDate(new \DateTime());
                $historique->setStatutOperation('Modification');
                
                if (!$demande) {
                    throw $this->createNotFoundException('Demande non trouvée.');
                }
            } else {
                $demande = new Demandes();
                $demande->setToken($token);
                $expiration = new \DateTimeImmutable('+24 hours');
                $demande->setTokenExpiration($expiration);
                $historique->setDemande($demande);
                $historique->setStatut($demande->getStatuts());
                $historique->setDate(new \DateTime());
                $historique->setStatutOperation('Création');
            }

            $choix = $data['replace_someone'];
            $statut_utilisateur = $data['statut'];
            $nom = $data['nom'];
            $prenom = $data['prenom'];

            if ($choix === 'oui') {
                $demande->setRemplacant(true);
                $demande->setNomRemplacant($data['remplacement_nom']);
                $demande->setPrenomRemplacant($data['remplacement_prenom']);
                $demande->setTelephoneRemplacant($data['telephone_avant_service']);
                $depart = $data['parti_rectorat'];
                if ($depart === true) {
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
            }

            if ($statut_utilisateur !== 'Titulaire') {
                $date_debut_contrat = $data['date_debut_contrat'];
                $date_fin_contrat = $data['date_fin_contrat'];
                $statut_utilisateur = $data['statut'];
                $user->setDateDebut($date_debut_contrat);
                $user->setDateFin($date_fin_contrat);
                $user->setStatutPersonne($statut_utilisateur);
            } else {
                $user->setStatutPersonne($statut_utilisateur);
            }

            $demande->setIDutilisateur($user);
            $demande->setDate(new \DateTime());
            $demande->setHeureSoumission(new \DateTime());
            $demande->setTitre('Demande d\'accès à un poste informatique');
            $demande->setStatuts('En attente');

            $entityManager->persist($user);
            $entityManager->persist($demande);

            // Ajouter une entrée dans l'historique
            
          
            $entityManager->persist($historique);

            $entityManager->flush();

            $url = $this->generateUrl('statuts_token', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
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
            $this->addFlash('success', 'Vous êtes redirigé. Vous pouvez accéder à cette page n\'importe quand depuis le lien dans votre boîte mail.');
            $mailer->send($email);

            return $this->redirectToRoute('statuts_token', ['token' => $token]);
        }

        return $this->render('formulaire/etape3.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 3,
            'total_steps' => 3,
            'token' => $token
        ]);
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

        // Pré-remplir les données pour le formulaire
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




    

    #[Route('/login_check', name: 'login_check')]
    public function check(): never
    {
        throw new \LogicException('Ce code ne devrait jamais être atteint');
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
