<?php

namespace App\Controller;

use App\Form\EmailVerificationFormType;
use App\Classe\MonApplication;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DemandesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use App\Entity\Demandes;
use Symfony\Component\Routing\Annotation\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verifyemail', name: 'verify_email')]
    public function verifyEmail(MailerInterface $mailer, DemandesRepository $demandesRepository ,MonApplication $monApplication, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EmailVerificationFormType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];

            $user = $userRepository->findOneBy(['email' => $email]);
            $demande = $demandesRepository->findOneBy(['IDutilisateur' => $user]);
            if ($this->redirection($email)) {
                $this->addFlash('warning', 'Votre compte a été redirigé vers la page de connexion car vous avez entrer une adresse email contenant @ac-guadeloupe.fr ce qui signifie que vous avez une adresse email académique.');
                return $this->redirectToRoute('ldap'); // Redirection vers RSA si email avec @ac-guadeloupe.fr  
            }


            if ($user && $demande) {
                $token = $demande->getToken();
                $url = $this->generateUrl('statuts_token', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            
                $email = (new Email())
                    ->from('noreply@ac-guadeloupe.fr')
                    ->to($user->getEmail())
                    ->subject('Accès à vos demandes')
                    ->html('
                    <p>Bonjour ' . $user->getPrenom() . ',</p>
                    <p>Vous pouvez consulter l\'état de vos demandes en cliquant sur le lien suivant : <a href="' . $url . '">Voir mes demandes</a></p>');
            
                $mailer->send($email);
            
                $this->addFlash('info', 'Un e-mail avec un lien pour accéder à vos demandes a été envoyé.');
            
                return $this->render('utilisateur/demande_existante.html.twig', [
                    'monApplication' => $monApplication,
                ]);
            } elseif ($user && !$demande) {
                $token = $user->getToken();
                $url = $this->generateUrl('formulairetest_etape1', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            
                $email = (new Email())
                    ->from('noreply@ac-guadeloupe.fr')
                    ->to($user->getEmail())
                    ->subject('Compléter votre demande')
                    ->html('
                    <p>Bonjour ' . $user->getPrenom() . ',</p>
                    <p>Veuillez compléter votre demande en cliquant sur le lien suivant : <a href="' . $url . '">Compléter ma demande</a></p>');
            
                $mailer->send($email);
            
                $this->addFlash('info', 'Votre compte existe déjà. Veuillez consulter votre boîte mail pour compléter le formulaire.');
            
                return $this->render('utilisateur/compte_existant.html.twig', [
                    'monApplication' => $monApplication,
                ]);
            }
                elseif(!$user) {
                    $this->addFlash('creation', 'Nous allons commencer la création de votre compte.');
                    // Rediriger vers le formulaire de création d'utilisateur
                    return $this->render('email_verification/redirect.html.twig', [
                        'redirect_url' => $this->generateUrl('user_creation', ['email' => $email]),
                        'monApplication' => $monApplication,
                        'email' => $email, // Inclure l'email pour l'afficher sur la page d'avertissement si nécessaire
                    ]);
                }
            
        }

        return $this->render('email_verification/index.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
        ]);
    }




    private function redirection(string $email): bool
    {
        $domain = explode('@', $email)[1];
    
        // Domaine qui entrainera la redirection
        return $domain === 'ac-guadeloupe.fr';
    }
}
