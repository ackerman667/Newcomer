<?php
/**
 * @file ProfilController.php
 * Controller Profil de l'application
 * @author Olivier Mauréaux
 * @date 12/2023
 */
namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProfilController extends AbstractController
{
    #[Route(path: '/profil', name: 'profil')]
    public function profil(MonApplication $monApplication)
    {
        return $this->render('profil/index.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'profil',
        ]);
    }
    /**
     * @Route("/profil/preferences", name="preferences")
     */
    public function preferences(MonApplication $monApplication)
    {
        return $this->render('profil/preferences.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'preferences',
        ]);
    }
        /**
     * @Route("/profil/roles", name="roles")
     */
    public function roles(MonApplication $monApplication)
    {
        return $this->render('profil/roles.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'roles',
        ]);
    }
}
