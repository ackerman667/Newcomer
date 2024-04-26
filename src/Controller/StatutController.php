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
    #[Route('/statuts', name: 'statuts')]
    public function index(MonApplication $monApplication,  EntityManagerInterface $entityManager ): Response
    {
        $user = $this->getUser();
        $demandes = $entityManager->getRepository(Demandes::class)->findBy(['IDutilisateur' => $user]);
        return $this->render('statuts/index.html.twig', [
            
            'demandes' => $demandes,
            "monApplication" => $monApplication,
        ]);
    }
}
