<?php
/**
 * @file MentionController.php
 * Controller Mention de l'application
 * @author Olivier Mauréaux
 * @date 12/2023
 */
namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MentionController extends AbstractController
{
    /**
     * @Route("/mention", name="mention")
     */
    public function index(MonApplication $monApplication)
    {
        return $this->render('mention/mention.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
}
