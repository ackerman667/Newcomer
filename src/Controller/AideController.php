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
use App\Service\UserRoleChecker;
use App\Security\UserInformation;
use App\Service\SuperUserChecker;


class AideController extends AbstractController
{
    private $security;
    private $roleChecker;
    private $superUserChecker;
    public function __construct(Security $security, UserRoleChecker $roleChecker,  SuperUserChecker $superUserChecker)
    {
        $this->security = $security;
        $this->roleChecker = $roleChecker;

        $this->superUserChecker = $superUserChecker;

        $this->isSuperUser = $this->superUserChecker->isSuperUser();

        $this->isValideur = $this->roleChecker->isUserValideur();
 
    }

    #[Route('formulaireldap/aide', name: 'aide')]
    public function index(MonApplication $monApplication)
    {


        $currentUser = $this->security->getUser();
        $uid = $currentUser->getUid();
        $isValideur = $this->roleChecker->isUserValideur();
        $isSuperUser  =  $this->superUserChecker->isSuperUser();
        return $this->render('aide/index.html.twig', [
            'monApplication' => $monApplication,
            'isValideur' => $isValideur,
            'isSuperUser' => $isSuperUser,
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
