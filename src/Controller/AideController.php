<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class AideController extends AbstractController
{
    /**
     * @Route("/aide", name="aide")
     */
    public function index()
    {
        return $this->render('aide/index.html.twig', [
            
        ]);
    }
}
