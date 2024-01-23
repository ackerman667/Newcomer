<?php

namespace App\Controller;

use App\Entity\User;  
use App\Form\InscriptionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use Doctrine\ORM\EntityManagerInterface;


class InscriptionController extends AbstractController
{
    #[Route("/inscription", name: "inscription")]
    public function register(Request $request, MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InscriptionType::class);
        $form->handleRequest($request);
        

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $plainPassword = $request->request->get('password');

        // Utilisez password_hash() pour hasher le mot de passe
        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);

            
            $user = new User();
            $user->setEmail($formData['email']);
            $user->setPassword($hashedPassword);  
         
            $entityManager->persist($user);
            $entityManager->flush();

           
            return $this->redirectToRoute('home');
        }

        return $this->render('inscription/index.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
        ]);
    }
}
