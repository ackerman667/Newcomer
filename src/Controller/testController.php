<?php

namespace App\Controller;


use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Annotations as Security;






class testController extends AbstractController
{ 
    #[Route('/testjwt', name: 'testjwt')]
    #[Security\IsGranted('ROLE_USER')]
   
    public function index(): Response
    {
        return $this->render('pagetest.html.twig');
    }
}
