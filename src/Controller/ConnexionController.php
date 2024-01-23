<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Form\ConnexionType;
use App\Classe\MonApplication;

class ConnexionController extends AbstractController
{
    #[Route("/connexion", name:"connexion")]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, Security $security, MonApplication $monApplication): Response
    {
        
        if ($security->getUser()) {
            return $this->redirectToRoute('profil'); 
        }

        
        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        $form = $this->createForm(ConnexionType::class, ['email' => $lastUsername]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            
            return $this->redirectToRoute('profil'); 
        }

        // Rendre la vue du formulaire de connexion
        return $this->render('connexion/index.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'form' => $form->createView(),
            'monApplication' => $monApplication,
        ]);
    }

    #[Route("/logout", name:"app_logout")]
    public function logout()
    {
        // Cette méthode ne sera exécutée car la route est gérée par Symfony Security
        throw new \Exception('This should never be reached!');
    }

    // ... (autres actions)
}
