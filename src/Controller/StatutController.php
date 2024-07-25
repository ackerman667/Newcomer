<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use App\Entity\Ressources;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;

class StatutController extends AbstractController
{
    #[Route('formulaireldap/statuts/{token}', name: 'statuts_token_ldap')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager, $token): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
        //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        // }

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

        // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
        //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        // }
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
        $user = $demande->getIDutilisateur();

        return $this->render('consult/index.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'monApplication' => $monApplication,
            'ressources' => $ressources,
        ]);
    }



    #[Route('formulaireldap/demande/pdf/{token}', name: 'demande_pdf_ldap')]
    public function generatePdf($token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        // if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
        //     throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        // }

        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
        $user = $demande->getIDutilisateur();

        // Configurer Dompdf selon vos besoins
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        // Récupérer le contenu HTML de votre template
        $html = $this->renderView('consult/pdf.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'ressources' => $ressources,
        ]);

        // Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);

        // (Optionnel) Définir le format du papier et l'orientation
        $dompdf->setPaper('A4', 'portrait');

        // Rendre le PDF
        $dompdf->render();

        // Envoyer le PDF au navigateur
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="demande.pdf"',
        ]);
    }


}
