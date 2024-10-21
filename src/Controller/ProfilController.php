<?php

namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security; 
use App\Security\UserInformation;

class ProfilController extends AbstractController
{
    private Security $security; 

 
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    // Méthode  pour récupérer les informations de l'utilisateur
    private function getUserInfo(): array
    {
        $user = $this->security->getUser();
        $userInformation = new UserInformation(); 
        $infos_user = $userInformation->getUserInformation($user);

        return [
            'nom' => $infos_user['sn'],
            'prenom' => $infos_user['givenname'],
            'email' => $infos_user['mail'],
            'dateNaissance' => $infos_user['datenaissance'],
            'uid' => $infos_user['uid'],
            'codecivilite'=> $infos_user['codecivilite'],
        ];
    }

    #[Route(path: '/profil', name: 'profil')]
    public function profil(MonApplication $monApplication)
    {
        
        $userInfo = $this->getUserInfo();

        return $this->render('profil/index.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'profil',
            'user' => $userInfo, 
        ]);
    }

    #[Route("/profil/preferences", name: "preferences")]
    public function preferences(MonApplication $monApplication)
    {
      
        $userInfo = $this->getUserInfo();

        return $this->render('profil/preferences.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'preferences',
            'user' => $userInfo, 
        ]);
    }

    #[Route("/profil/roles", name: "roles")]
    public function roles(MonApplication $monApplication)
    {
        
        $userInfo = $this->getUserInfo();

        return $this->render('profil/roles.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'roles',
            'user' => $userInfo, 
        ]);
    }
}
