<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use Doctrine\ORM\EntityManagerInterface;

class StatutController extends AbstractController
{
    #[Route('formulaireldap/statuts/{token}', name: 'statuts_token_ldap')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager, $token): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
            throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        }

        $user = $demande->getIDutilisateur();
        $demandes = $entityManager->getRepository(Demandes::class)->findBy(['IDutilisateur' => $user]);

        return $this->render('statuts/token_ldap.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
            'user' => $user,
        ]);
    }

    #[Route('formulaireldap/demande/consult/{token}', name: 'demande_consult_ldap')]
    public function consult(MonApplication $monApplication, $token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
            throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        }

        $user = $demande->getIDutilisateur();

        return $this->render('consult/index.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'monApplication' => $monApplication,
        ]);
    }
}
