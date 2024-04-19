<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Classe\MonApplication;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;



class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils,MonApplication $monApplication ): Response
    {
         if ($this->getUser()) {
             return $this->redirectToRoute('profil');
             $this->addFlash('error', 'Vous n\'avez pas accès à cette page car vous êtes déjà connecté.');
        }

        
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error ,"monApplication" => $monApplication]);
        
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
       
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
