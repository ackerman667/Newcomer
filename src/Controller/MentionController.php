<?php
/**
 * @file MentionController.php
 * Controller Mention de l'application
 * @author Olivier Mauréaux
 * @date 12/2023
 */
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class MentionController extends AbstractController
{
    /**
     * @Route("/mention", name="mention")
     */
    public function index()
    {
        return $this->render('mention/mention.html.twig', [

        ]);
    }
}
