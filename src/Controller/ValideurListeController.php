<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\Demandes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\HistoriqueDemande;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class ValideurListeController extends AbstractController
{
    #[Route('/listedemandes', name: 'listedemandes')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        $uid = $user->getUid();
        $demandes = $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
            ->where('d.uid_valideur = :uid')
            ->setParameter('uid', $uid)
            ->getQuery()
            ->getResult();

        return $this->render('valideur/index.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
        ]);
    }

    #[Route('/validerdemande/{id}', name: 'valider_demande', methods: ['POST'])]
    public function validerDemande(int $id, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $demande->setStatuts('Validé');
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut('Validée');
        $historique->setStatutOperation('Validation');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);

        $entityManager->flush();

        return $this->redirectToRoute('listedemandes');
    }
}
  

