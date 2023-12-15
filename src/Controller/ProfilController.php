<?php
/**
 * @file HomeController.php
 * Controller Profil de l'application
 * @author Olivier Mauréaux
 * @date 12/2023
 */
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ProfilController extends AbstractController
{
    /**
     * @Route("/profil", name="profil")
     */
    public function profil()
    {
        return $this->render('profil/index.html.twig', [
            'page' => 'profil',
        ]);
    }
    /**
     * @Route("/profil/preferences", name="preferences")
     */
    public function preferences()
    {
        return $this->render('profil/preferences.html.twig', [
            'page' => 'preferences',
        ]);
    }
        /**
     * @Route("/profil/roles", name="roles")
     */
    public function roles()
    {
        return $this->render('profil/roles.html.twig', [
            'page' => 'roles',
        ]);
    }
}
