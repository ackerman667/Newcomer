<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Demandes;

class DetanController extends AbstractController {

#[Route('formulaireldap/demandesvalidees', name: 'demandes_validees')]
public function demandesValidees(MonApplication $monApplication, EntityManagerInterface $entityManager): Response
{
    $statut = 'Validé';
    $demandes = $entityManager->getRepository(Demandes::class)->findBy(['statuts' => $statut]);

    return $this->render('assistance/demandes_validees.html.twig', [
        'demandes' => $demandes,
        'monApplication' => $monApplication,
    ]);
}
}