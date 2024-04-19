<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\Demandes;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Dompdf\Dompdf;
use Symfony\Component\Security\Core\Security;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Form\DemandeFormType;
use App\Security\UserInformation;

class FormulaireLdapController extends AbstractController
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }



    #[Route('/formulaireldap', name: 'formulaireldap')]
    public function index(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, HttpClientInterface $httpClient): Response
    {
        
        $user = $this->security->getUser();

        $userInformation = new UserInformation();

        // Appeler la méthode getUserInformation
        $infos_user = $userInformation->getUserInformation($user);
        dump($ldapUsername);
        
        

        $form = $this->createForm(DemandeFormType::class, [
            'nom' => $infos_user['cn'],
            
        ]);
        


      
        $form->handleRequest($request);

        return $this->render('formulaireldap/index.html.twig', [
            'form' => $form->createView(), // Transmettre le formulaire à la vue
            'monApplication' => $monApplication,
        ]);
        
    }
    
}
?>
