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
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;

class FormulaireTestController extends AbstractController
{
    
    #[Route('/formulairetest/etape1', name: 'formulairetest_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, SessionInterface $session): Response
    {
        $demande = new Demandes();
        $user = new User();
        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $choix = $data['bouton_radio'];

            // Traiter les données en fonction de la sélection du bouton radio
            if ($choix === 'oui') {
                // Champ spécifique pour le bouton 'oui'
                $data['champ_specifique'] = 'Valeur pour oui';
            } else {
                // Champ spécifique pour le bouton 'non'
                $data['champ_specifique'] = null; // ou autre valeur par défaut
            }
            $data = $form->getData();
            $session->set('form_data', $data);
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
    public function etape2(MonApplication $monApplication, Request $request, SessionInterface $session, HttpClientInterface $httpClient): Response
    {
         // Récupérer les données de la session
         $data = $session->get('form_data', []);

         // Créer le formulaire avec les données de la session
        
 
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
         $servicesDropdownData = $this->transformServicesForDropdown($services);
         dump($servicesDropdownData);
         $form = $this->createForm(DemandeEtape2FormType::class, $data, [
            'services' => $servicesDropdownData,
        ]);
         // Gérer la soumission du formulaire
         $form->handleRequest($request);
 
         if ($form->isSubmitted() && $form->isValid()) {
             // Sauvegarder les données dans la session ou faire autre chose ici
             $data = $form->getData();
             $session->set('form_data', $data);
 
             return $this->redirectToRoute('formulairetest_etape3');
         }
 
         // Afficher le formulaire
         return $this->render('formulaire/etape2.html.twig', [
             'form' => $form->createView(),
             'monApplication' => $monApplication,
             'servicesDropdownData' => $servicesDropdownData, // Passer les services au template Twig
             'current_step' => 2,
             'total_steps' => 3,
         ]);
    }

    #[Route('/formulairetest/etape3', name: 'formulairetest_etape3')]
    public function etape3(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer, LoginLinkHandlerInterface $loginLinkHandler, NotifierInterface $notifier): Response
    {
        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape3FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Sauvegarder les données dans la base de données
            
            $user->setNom($data['nom']);
            $user->setPrenom($data['prenom']);
            $user->setEmail($data['email']);
            $user->setFonction($data['fonction']);


            $destinataire = $data['email'];
            dump($destinataire);
            $demande->setIDutilisateur($user);

            $demande->setDate(new \DateTime());
            $demande->setHeureSoumission(new \DateTime());
            $demande->setTitre('Demande d\'accès à un poste informatique TEST!!!!!');
            $demande->setStatuts('En attente');
            $token = bin2hex(random_bytes(32));
            $expiration = new \DateTimeImmutable('+24 hours');
            $demande->setToken($token);
            
            $demande->setTokenExpiration($expiration);

            $entityManager->persist($demande);
            $entityManager->persist($user);
            $entityManager->flush();

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);
            $loginLink = $loginLinkDetails->getUrl();
            $targetUrl = $this->generateUrl('aide'); 
             $loginLink .= '?target=' . urlencode($targetUrl);

            // Envoi de l'email avec Symfony Mailer
            $email = (new Email())
                ->from('noreply@ac-guadeloupe.fr')
                ->to($destinataire)
                ->subject('Votre lien de connexion')
                ->cc('Nicolas.Barbeu@ac-guadeloupe.fr')
                ->text('Voici votre lien de connexion :')
                ->html('<p>Voici votre lien de connexion :</p><p><a href="' . $loginLink . '">Cliquez ici pour vous connecter</a></p>');

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

                return $this->render('security/lien.html.twig',[
                    'monApplication' => $monApplication,
                ]);
            }

            
        }

        return $this->render('security/demande_connexion.html.twig',[
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
