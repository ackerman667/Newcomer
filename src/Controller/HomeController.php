<?php
/**
 * @file HomeController.php
 * Controller Home de l'application
 * @author Olivier Mauréaux
 * @date 12/2023
 */
namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    /**
     * @fn public function index(): Response
     * Appel de la route home à l'adresse home/index.html.twig
     * @details
     * Le twig va permettre d'être redirigé vers la 1ère route \link MonController.php MonController.php \endlink.
     */
     
     /**
     * @Route("/", name="home")
     */
    public function index(MonApplication $monApplication): Response
    {
        return $this->render('home/index.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
}
