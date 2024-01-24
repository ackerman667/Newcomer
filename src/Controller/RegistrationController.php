<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Classe\MonApplication;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MonApplication $monApplication): Response
    {
        if ($this->getUser()) {
            // Si un utilisateur est connecté impossible d'accedeer a /register il sera redirigé vers profil
            $this->addFlash('error', 'Vous n\'avez pas accès à cette page car vous êtes déjà connecté.');
            return $this->redirectToRoute('profil'); 
        }
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          
            $email = $form->get('email')->getData();
            // Récupérer l'email
            if ($this->redirection($email)) {
                $this->addFlash('warning', 'Votre compte a été redirigé vers la page de connexion car vous avez entrer une adresse email contenant @ac-guadeloupe.fr ce qui signifie que vous avez une adresse email académique.');
                return $this->redirectToRoute('app_login'); // Redirection vers RSA si email avec @ac-guadeloupe.fr  !!! {# Ajoutez la futur redirection RSA#}
            }

                 // hasher le mot de passe
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();



          


            return $this->redirectToRoute('profil');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            "monApplication" => $monApplication,
        ]);
    }






    private function redirection(string $email): bool
{
    $domain = explode('@', $email)[1];

    // Domaine qui entrainera la redirection
    return $domain === 'ac-guadeloupe.fr';
}





}
