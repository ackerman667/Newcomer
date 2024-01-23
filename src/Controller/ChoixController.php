<?php
/**
 * @file FormulaireController.php
 * Controller Formulaire de l'application
 * @author Olivier Mauréaux
 * @date 12/2023
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
        return $this->render('choix/index.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
}
