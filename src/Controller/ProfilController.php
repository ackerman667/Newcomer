<?php

namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security; 
use App\Entity\User;
use App\Security\UserInformation;

class ProfilController extends AbstractController
{
    private Security $security; 
    private $requestStack;

 
    public function __construct(Security $security, RequestStack $requestStack)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
       
    }

    // Méthode  pour récupérer les informations de l'utilisateur
    private function getUserInfoLdap(): array
    {
        $session = $this->requestStack->getSession();
        $user = $this->security->getUser();
        $userInformation = new UserInformation(); 
        $infos_user = $userInformation->getUserInformation($user);
        $userUrlPortail = $session->get('user_urlportail');
       


        return [
            'nom' => $infos_user['sn'],
            'prenom' => $infos_user['givenname'],
            'email' => $infos_user['mail'],
            'dateNaissance' => $infos_user['datenaissance'],
            'uid' => $infos_user['uid'],
            'codecivilite'=> $infos_user['codecivilite'],
            
        ];
    }






   

    #[Route(path: 'formulaireldap/profil', name: 'profil')]
    public function profil(MonApplication $monApplication)
    {
        
        $userInfo = $this->getUserInfoLdap();

        return $this->render('profil/index.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'profil',
            'user' => $userInfo, 
            

        ]);
    }

    #[Route("formulaireldap/profil/preferences", name: "preferences")]
    public function preferences(MonApplication $monApplication)
    {
      
        $userInfo = $this->getUserInfoLdap();

        return $this->render('profil/preferences.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'preferences',
            'user' => $userInfo, 
        ]);
    }

    #[Route("formulaireldap/profil/roles", name: "roles")]
    public function roles(MonApplication $monApplication)
    {
        
        $userInfo = $this->getUserInfoLdap();

        return $this->render('profil/roles.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'roles',
            'user' => $userInfo, 
        ]);
    }





    // Partie externe

    private function getUserInfo(): array
    {
       
        $user = $this->getUser();
    
       
        return [
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getEmail(),
            'dateNaissance' => $user->getDateDeNaissance()?->format('Y-m-d'),
            
        ];
    }

    #[Route(path: 'formulaireext/profil', name: 'profil_')]
    public function profil2(MonApplication $monApplication)
    {
        
        $userInfo = $this->getUserInfo();

        return $this->render('profil/index.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'profil',
            'user' => $userInfo, 
            

        ]);
    }

    #[Route("formulaireext/profil/preferences", name: "preferences_")]
    public function preferences2(MonApplication $monApplication)
    {
      
        $userInfo = $this->getUserInfo();

        return $this->render('profil/preferences.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'preferences',
            'user' => $userInfo, 
        ]);
    }

    #[Route("formulaireext/profil/roles", name: "roles_")]
    public function roles2(MonApplication $monApplication)
    {
        
        $userInfo = $this->getUserInfo();

        return $this->render('profil/roles.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'roles',
            'user' => $userInfo, 
        ]);
    }
}
