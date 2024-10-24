<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\User;
use App\Entity\Demandes;
use Symfony\Component\Routing\Annotation\Route;

use App\Security\UserInformation;
use Symfony\Bundle\SecurityBundle\Security;

class LdapController extends AbstractController
{
    private $security;
    private $requestStack;

    public function __construct(Security $security, RequestStack $requestStack)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    #[Route('/formulaireldap/sessionstart', name: 'ldap')]
    public function Ldap(MonApplication $monApplication, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {

        $user = $this->security->getUser();
        $session = $this->requestStack->getSession();
        $session->set('ldap_authenticated', true);
        $session->set('user_urlportail', $user->getUrlPortail()); 
        $session->set('user_urllogout', $user->getUrllogout()); 
        $session->set('user_urlproxy', $user->getUrlproxy()); 
        return $this->redirectToRoute('liste_demandes');
       


    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): Response
    {
       
        $session = $this->requestStack->getSession();
        
        
        $session->remove('ldap_authenticated');
        $session->clear();

        
        return $this->redirectToRoute('profil');
    }
}