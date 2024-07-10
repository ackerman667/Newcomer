<?php
namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\Demandes;
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
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Notifier\NotifierInterface;

class FormulaireTestController extends AbstractController
{

    #[Route('/formulairetest/etape1', name: 'formulairetest_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        // Initialiser la demande et l'utilisateur s'ils n'existent pas dans la session
        

        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $session->set('form_data', $data);
          // Sauvegarder les modifications de la demande

            return $this->redirectToRoute('formulairetest_etape2');
        }

        return $this->render('formulaire/etape1.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
        ]);
    }

    #[Route('/formulairetest/etape2', name: 'formulairetest_etape2')]
    public function etape2(MonApplication $monApplication, Request $request, SessionInterface $session, HttpClientInterface $httpClient, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les données de la session
        $data = $session->get('form_data', []);

        // Récupérer l'utilisateur et la demande depuis la session
    

        // Récupérer les services depuis l'API
        $apiUrl = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/services';
        $apiToken = 'b97b055g210125afb4c5f507dc823958ff18dfa56a12c7n12agch8db58e21767';
        $response = $httpClient->request('GET', $apiUrl, [
            'headers' => [
                'x-auth-token' => $apiToken,
                'Accept' => 'application/json',
            ],
        ]);
       

        $services = $response->toArray();
        dump($services);
        $servicesDropdownData = $this->transformServicesForDropdown($services);

        $form = $this->createForm(DemandeEtape2FormType::class, $data, [
            'services' => $servicesDropdownData,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
// Sauvegarder les modifications de l'utilisateur

            $session->set('form_data', $data);

            return $this->redirectToRoute('formulairetest_etape3');
        }

        return $this->render('formulaire/etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'servicesDropdownData' => $servicesDropdownData,
            'current_step' => 2,
            'total_steps' => 3,
        ]);
    }

    #[Route('/formulairetest/etape3', name: 'formulairetest_etape3')]
    public function etape3(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer, LoginLinkHandlerInterface $loginLinkHandler, NotifierInterface $notifier): Response
    {
        // Récupérer les données de la session
        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape3FormType::class, $data);
        $form->handleRequest($request);

        // Récupérer l'utilisateur et la demande depuis la session
       

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $nom = $data['nom'];
            $prenom = $data['prenom'];
            $demande = new Demandes();
            $user = new User();
            $choix = $data['replace_someone'];
            $date_debut_contrat=$data['date_debut_contrat'];
            $date_fin_contrat=$data['date_fin_contrat'];
            $statut_utilisateur=$data['statut'];
            if ($choix === 'oui') {
                $demande->setRemplacant(true);
                $demande->setNomRemplacant($data['remplacement_nom']);
                $demande->setPrenomRemplacant($data['remplacement_prenom']);
                $demande->setTelephoneRemplacant($data['telephone_avant_service']);
                $depart = $data['parti_rectorat'];
                if ($depart === true) {
                    $demande->setDepart(true);
                $demande->setAffectationRemplacant($data['nouvelle_affectation_service']);
                } else {
                    $demande->setDepart(false);
                }
            } else 
            {
                $demande->setRemplacant(false);
            }

            if($statut_utilisateur !== 'Titulaire') {
                $user->setDateDebut($date_debut_contrat);
                $user->setDateFin($date_fin_contrat);
            }

            
            
            $user->setNom($data['nom']);
            $user->setPrenom($data['prenom']);
            $user->setEmail($data['email']);
            $user->setFonction($data['fonction']);
            $user->setStatutPersonne($data['statut']);
            $user->setDateDeNaissance($data['date_de_naissance']);
            $demande->setNomRemplacant($data['remplacement_nom']);
            $demande->setPrenomRemplacant($data['remplacement_prenom']);
            $demande->setTelephoneRemplacant($data['telephone_avant_service']);
            $demande->setAffectationRemplacant($data['nouvelle_affectation_service']);
            $demande->setIDutilisateur($user);
            $demande->setDate(new \DateTime());
            $demande->setHeureSoumission(new \DateTime());
            $demande->setTitre('Demande d\'accès à un poste informatique TEST!!!!!');
            $demande->setStatuts('En attente');
            $entityManager->persist($user);
            $entityManager->persist($demande);
            

            $entityManager->flush();
           
            $session->clear(); // Sauvegarder les modifications de la demande et de l'utilisateur

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);
            $loginLink = $loginLinkDetails->getUrl();

            // Envoi de l'email avec Symfony Mailer
            $email = (new Email())
                ->from('noreply@ac-guadeloupe.fr')
                ->to($user->getEmail())
                ->subject('Votre lien de connexion')
                ->cc('Nicolas.Barbeu@ac-guadeloupe.fr')
                ->text('Voici votre lien de connexion :')
                ->html('
                <p>Bonjour ' . $nom. ' ' . $prenom . ',</p>
                <p>Nous avons bien recu votre demande d\'accès à un poste de travail informatique.</p>
                <p>Pour  accéder à votre compte, veuillez cliquer sur le lien ci-dessous :</p>
                <p><a href="' . $loginLink . '">Cliquez ici pour vous connecter</a></p>
                <p>Ce lien est valable pour une durée de 24 heures. Si vous n\'avez pas demandé cet accès, veuillez ignorer cet e-mail.</p>
                <p>Bien cordialement,</p>
                <p><strong>Votre équipe informatique</strong></p>
            ');

            $mailer->send($email);

            // Rediriger vers une page de confirmation ou autre
            return $this->redirectToRoute('home');
        }

        return $this->render('formulaire/etape3.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 3,
            'total_steps' => 3,
        ]);
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

                // Envoi de l'email avec Symfony Mailer
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

    #[Route('/demande/consult/{token}', name: 'demande_consult')]
    public function consult(MonApplication $monApplication, $token, EntityManagerInterface $entityManager)
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
            throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        }

        return $this->render('consult/index.html.twig', [
            'demande' => $demande,
            'monApplication' => $monApplication,
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
