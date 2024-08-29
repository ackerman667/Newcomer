<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;
use App\Entity\Demandes;
use Symfony\Component\Routing\Annotation\Route;

use App\Security\UserInformation;
use Symfony\Component\Security\Core\Security;

class LdapController extends AbstractController
{
    private $security;
  

    public function __construct(Security $security)
    {
        $this->security = $security;

    }  

    #[Route('/formulaireldap/statuts', name: 'ldap')]
    public function Ldap(MonApplication $monApplication, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {

        $user = $this->security->getUser();
        $userInformation = new UserInformation();
        // dump($user);
         $infos_user = $userInformation->getUserInformation($user);
        //  dump($infos_user);
        $uid = $infos_user['uid'];
        // $uid = $user.getUid();
        dump($uid);
        $user_bdd= $entityManager->getRepository(User::class)->findOneBy(['uid' => $uid]);
        dump($user_bdd);
        if($user_bdd) {
             $id_demandes=$user_bdd->getId();
             $demande = $entityManager->getRepository(Demandes::class)->findBy(['IDutilisateur' => $id_demandes]);
             dump($demande);$token = $demande[0]->getToken();

            dump($token);
            return $this->redirectToRoute('statuts_token_ldap', ['token' => $token]);

        } else {
            return $this->redirectToRoute('formulaireldap_etape1');
    
     
        }
      
}
}