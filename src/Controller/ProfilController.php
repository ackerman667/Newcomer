<?php

namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Security; 
use App\Security\UserInformation;// Import pour Security

class ProfilController extends AbstractController
{
    private Security $security; // Ajouter la propriété Security

    // Injecter Security via le constructeur
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    // Méthode privée pour récupérer les informations de l'utilisateur
    private function getUserInfo(): array
    {
        $user = $this->security->getUser();
        $userInformation = new UserInformation(); // Assurez-vous que cette classe est bien définie et importée
        $infos_user = $userInformation->getUserInformation($user);

        return [
            'nom' => $infos_user['sn'],
            'prenom' => $infos_user['givenname'],
            'email' => $infos_user['mail'],
            'dateNaissance' => $infos_user['datenaissance'],
            'uid' => $infos_user['uid'],
        ];
    }

    #[Route(path: 'formulaireldap/profil', name: 'profil')]
    public function profil(MonApplication $monApplication)
    {
        // Utiliser la méthode privée pour récupérer les infos utilisateur
        $userInfo = $this->getUserInfo();

        return $this->render('profil/index.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'profil',
            'user' => $userInfo, // Ajouter les informations utilisateur
        ]);
    }

    #[Route("formulaireldap/profil/preferences", name: "preferences")]
    public function preferences(MonApplication $monApplication)
    {
        // Utiliser la méthode privée pour récupérer les infos utilisateur
        $userInfo = $this->getUserInfo();

        return $this->render('profil/preferences.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'preferences',
            'user' => $userInfo, // Ajouter les informations utilisateur
        ]);
    }

    #[Route("formulaireldap/profil/roles", name: "roles")]
    public function roles(MonApplication $monApplication)
    {
        // Utiliser la méthode privée pour récupérer les infos utilisateur
        $userInfo = $this->getUserInfo();

        return $this->render('profil/roles.html.twig', [
            'monApplication' => $monApplication,
            'page' => 'roles',
            'user' => $userInfo, // Ajouter les informations utilisateur
        ]);
    }
}
