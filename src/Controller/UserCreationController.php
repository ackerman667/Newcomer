<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserCreationFormType;
use App\Classe\MonApplication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserCreationController extends AbstractController
{
    #[Route('/create-user', name: 'user_creation')]
    public function createUser(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        
        $form = $this->createForm(UserCreationFormType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = new User();
            $user->setCompteActif(false);
            $nom = $form->get('nom')->getData();
            $prenom = $form->get('prenom')->getData();
            $fonction = $form->get('fonction')->getData();
            $email_user = $form->get('email')->getData();
            $date_de_naissance = $form->get('date_de_naissance')->getData();
            
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setFonction($fonction);
            $user->setDateDeNaissance($date_de_naissance);
            $user->setFonction($fonction);
            $user->setEmail($email_user);
            $token = bin2hex(random_bytes(32));
            $expiration = new \DateTimeImmutable('+24 hours');
            $user->setToken($token);
            $user->setTokenExpiration($expiration);
            $entityManager->persist($user);
            $entityManager->flush();

            $activationLink = $this->generateUrl('activate_account', [ 'token' => $user->getToken() ], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new Email())
                ->from('noreply@ac-guadeloupe.fr')
                ->to($user->getEmail())
                ->subject('Activation de votre compte')
                ->html('<p>Bonjour ' . $user->getPrenom() . ',</p><p>Veuillez activer votre compte en cliquant sur le lien suivant : <a href="' . $activationLink . '">Activer mon compte</a></p>');

            $mailer->send($email);

            $this->addFlash('success', 'Votre compte a bien été créé. Veuillez l\'activer par mail.');

            return $this->redirectToRoute('user_creation_confirmation');
        }

        return $this->render('user_creation/index.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,

        ]);
    }
    #[Route('/create-user/confirmation', name: 'user_creation_confirmation')]
    public function userCreationConfirmation(MonApplication $monApplication): Response
    {
        return $this->render('user_creation/confirmation.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
}
