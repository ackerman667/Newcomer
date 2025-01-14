<?php
/**
 * @file AideController.php
 * Controller Aide de l'application
 * @author Olivier Mauréaux
 * @date 12/2023
 */
namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AideController extends AbstractController
{
   
    #[Route('formulaireldap/aide', name: 'aide')]
    public function index(MonApplication $monApplication)
    {
        return $this->render('aide/index.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }


    #[Route('formulaireext/aide', name: 'aide_externe')]
    public function index2(MonApplication $monApplication, SessionInterface $session)
    {

        
      
        return $this->render('aide/index2.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }

    #[Route('/aide', name: 'aide_externe_anon')]
    public function index3(MonApplication $monApplication, SessionInterface $session)
    {

        
      
        return $this->render('aide/index2.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
}
