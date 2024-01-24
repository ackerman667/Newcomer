<?php
/**
 * @file ChoixController.php
 * Controller Choix Je dispose deja d'un compte / Je n'ai pas de compte de l'application
 * @author Barbeu Nicolas
 * @date 01/2024
 */
namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ChoixController extends AbstractController
{
    #[Route('/choix', name: 'choix')]
    public function index(MonApplication $monApplication): Response
    {
        if ($this->getUser()) {
            // Si un utilisateur est connecté impossible d'accedeer a /choix il sera redirigé vers profil
            $this->addFlash('error', 'Vous n\'avez pas accès à cette page car vous êtes déjà connecté.');
            return $this->redirectToRoute('profil'); 
        }
        return $this->render('choix/index.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
}
