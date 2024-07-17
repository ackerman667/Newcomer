<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\Demandes;
use App\Entity\Ressources;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\HistoriqueDemande;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\DemandeEtape3FormType;
use Symfony\Component\Security\Core\Security;

class ValideurListeController extends AbstractController
{
    #[Route('formulaireldap/listedemandes', name: 'listedemandes')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        $uid = $user->getUid();
        $demandes = $entityManager->getRepository(Demandes::class)->createQueryBuilder('d')
            ->where('d.uid_valideur = :uid')
            ->setParameter('uid', $uid)
            ->getQuery()
            ->getResult();

        return $this->render('valideur/index.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
        ]);
    }

    #[Route('formulaireldap/validerdemande/{id}', name: 'valider_demande', methods: ['POST'])]
    public function validerDemande(int $id, EntityManagerInterface $entityManager,  MailerInterface $mailer): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $demande->setStatuts('Validé');
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut('Validée');
        $historique->setStatutOperation('Validation');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);

        $entityManager->flush();

        $email = (new Email())
            ->from('noreply@ac-guadeloupe.fr')
            ->to($demande->getIDutilisateur()->getEmail())
            ->subject('Votre demande a été validée')
            ->html('<p>Votre demande a été validée.</p>');

        $mailer->send($email);


        return $this->redirectToRoute('listedemandes');
    }


    #[Route('formulaireldap/commenterdemande/{id}', name: 'commenter_demande', methods: ['POST'])]
    public function commenterDemande(int $id, Request $request, EntityManagerInterface $entityManager,  MailerInterface $mailer): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $commentaire = $request->request->get('commentaire');
        $demande->setCommentaire($commentaire);
        
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
        $historique->setStatut($demande->getStatuts());
        $historique->setStatutOperation('Commentaire');
        $historique->setDate(new \DateTime());
        $entityManager->persist($historique);

        $entityManager->flush();
          // Envoyer un email de notification
          $email = (new Email())
          ->from('noreply@ac-guadeloupe.fr')
          ->to($demande->getIDutilisateur()->getEmail())
          ->subject('Votre demande a reçu un commentaire')
          ->html('<p>Votre demande a reçu un commentaire : ' . $commentaire . '</p>');

      $mailer->send($email);

        return $this->redirectToRoute('listedemandes');
    }

    #[Route('formulaire/demande/visualiser/{id}', name: 'visualiser_demande')]
    public function visualiserDemande(MonApplication $monApplication, int $id, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande->getId()]);
        dump($ressources);
        $user = $demande->getIDutilisateur();
        return $this->render('valideur/visualiser.html.twig', [
            'demande' => $demande,
            'monApplication' => $monApplication,
            'user' => $user,
            'ressources' => $ressources,
            
        ]);
    }

    #[Route('formulaireldap/modifierdemandes/{id}', name: 'modifier_demandesvalideur')]
    public function editDemande(int $id, Request $request, EntityManagerInterface $entityManager, MonApplication $monApplication): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }

        $form = $this->createForm(DemandeEtape3FormType::class, $demande);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La demande a été modifiée avec succès.');

            return $this->redirectToRoute('listedemandes');
        }

        return $this->render('valideur/modifier.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
        ]);
    }

}
  

