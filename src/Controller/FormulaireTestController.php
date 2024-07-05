<?php


// src/Controller/FormulaireTestController.php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\Demandes;
use App\Form\DemandeEtape1FormType;
use App\Form\DemandeEtape2FormType;
use App\Form\DemandeEtape3FormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;

class FormulaireTestController extends AbstractController
{
    #[Route('/formulairetest/etape1', name: 'formulairetest_etape1')]
    public function etape1(MonApplication $monApplication, Request $request, SessionInterface $session): Response
    {
        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape1FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $session->set('form_data', $data);
            return $this->redirectToRoute('formulairetest_etape2');
        }

        return $this->render('formulaire/etape1.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 1,
            'total_steps' => 3,
        ]);
    }

    #[Route('/formulairetest/etape2', name: 'formulairetest_etape2')]
    public function etape2(MonApplication $monApplication, Request $request, SessionInterface $session): Response
    {
        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape2FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $session->set('form_data', $data);
            return $this->redirectToRoute('formulairetest_etape3');
        }

        return $this->render('formulaire/etape2.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 2,
            'total_steps' => 3,
        ]);
    }

    #[Route('/formulairetest/etape3', name: 'formulairetest_etape3')]
    public function etape3(MonApplication $monApplication, Request $request, SessionInterface $session, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $data = $session->get('form_data', []);
        $form = $this->createForm(DemandeEtape3FormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Sauvegarder les données dans la base de données
            $demande = new Demandes();
            $user = new User();
            $user->setNom($data['nom']);
            $user->setPrenom($data['prenom']);
            $user->setEmail($data['email']);

            $demande->setDate(new \DateTime());
            $demande->setHeureSoumission(new \DateTime());
            $demande->setTitre('Demande d\'accès à un poste informatique TEST!!!!!');
            $demande->setStatuts('En attente');
            $token = bin2hex(random_bytes(32));
            $expiration = new \DateTimeImmutable('+24 hours');
            $demande->setToken($token);
            $demande->setTokenExpiration($expiration);

            $entityManager->persist($demande);
            $entityManager->persist($user);
            $entityManager->flush();
                $destinataire = $data['email'];
            $url = $this->generateUrl('demande_consult', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            $email = (new Email())
                ->from('noreply@ac-guadeloupe.fr')
                ->to($destinataire)
                ->subject('TEST')
                ->cc('Nicolas.Barbeu@ac-guadeloupe.fr')
                ->text('This is the text version of the email.')
                ->html('<p>This is the HTML version of the email.</p><p><a href="' . $url . '">Consultez votre demande</a></p>');
            $sentEmail = $mailer->send($email);   
          

            // Rediriger vers une page de confirmation ou autre
            return $this->redirectToRoute('demande_consult');
        }

        return $this->render('formulaire/etape3.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
            'current_step' => 3,
            'total_steps' => 3,
        ]);
    }

    // #[Route('/formulairetest/confirmation', name: 'formulairetest_confirmation')]
    // public function confirmation(): Response
    // {
    //     return $this->render('home');
    // }

    #[Route('/demande/consult/{token}', name: 'demande_consult')]
    public function consult(MonApplication $monApplication,$token, EntityManagerInterface $entityManager)
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);

        if (!$demande || $demande->getTokenExpiration() < new \DateTime()) {
            throw $this->createNotFoundException('Le lien a expiré ou est invalide.');
        }

        return $this->render('consult/index.html.twig', [
            'demande' => $demande,
            'monApplication' => $monApplication,

        ]);
    }
}
