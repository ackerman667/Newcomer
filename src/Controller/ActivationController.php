<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ActivationController extends AbstractController
{
    #[Route('/activate-account/{token}', name: 'activate_account')]
    public function activateAccount(MonApplication $monApplication, $token, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['token' => $token]);

        if ($user && $user->getTokenExpiration() >= new \DateTime()) {
            // Activer le compte utilisateur
            $user->setCompteActif(true);
        
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte a été activé avec succès.');

            return $this->render('activation/success_activation.html.twig', [
                'token' => $token,
                'monApplication' => $monApplication,
            ]);
        }

        // Rendu si le token est invalide ou expiré
        return $this->render('activation/invalid_token.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
}
