<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Entity\User;
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
            return $this->render('activation/confirm_activation.html.twig', [
                'token' => $token,
                'monApplication' => $monApplication,
            ]);
        }

        return $this->render('activation/invalid_token.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }

    #[Route('/confirm-activation/{token}', name: 'confirm_activation')]
    public function confirmActivation(MonApplication $monApplication, $token, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['token' => $token]);

        if ($user && $user->getTokenExpiration() >= new \DateTime()) {
            $user->setCompteActif(true);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte a été activé avec succès. Vous allez être redirigé vers le formulaire de demande.');

            return $this->render('activation/success_activation.html.twig', [
                'token' => $token,
                'monApplication' => $monApplication,
            ]);
        }

        return $this->render('activation/invalid_token.html.twig', [
            'monApplication' => $monApplication,
            'token' => $token,
        ]);
    }
}
