<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class SecurityLdapController extends AbstractController
{
    #[Route('/loginldap', name: 'loginldap')]
    public function rsaLogin(): RedirectResponse
    {
        // Rediriger vers la page d'accueil après la connexion RSA réussie
        return $this->redirectToRoute('home');
    }
}
