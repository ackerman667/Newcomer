<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\TemporaryData;

class SessionTimeoutSubscriber implements EventSubscriberInterface
{
    private $entityManager;
    private $mailer;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Vérifiez si la session utilisateur est active
        $session = $request->getSession();
        if ((!$session || !$session->has('externe_auth') || !$session->has('externe_token')) || $this->security->getUser()) {
            // Action si aucune session externe active et aucun utilisateur connecté via sécurité
            return;
        }

        // Récupérer le token actuel
        $token = $session->get('externe_token');

        // Vérifier si l'utilisateur existe dans la base
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['token' => $token]);
        if (!$user) {
            $response = new RedirectResponse('/testnewcomer/session-expired');
            $event->setResponse($response);
            return;
        }

        // if (!$lastActivity) {}

        // Vérifier l'expiration du token
        $lastActivity = $session->get('last_activity', time());
        if ((time() - $lastActivity) > 60  ) { // 20 minutes = 1200 secondes

            $temporaryDataEntries = $this->entityManager->getRepository(\App\Entity\TemporaryData::class)
            ->findBy(['user' => $user]);

        foreach ($temporaryDataEntries as $entry) {
            $this->entityManager->remove($entry);
        }
        $this->entityManager->flush();
            // Générer un nouveau token
            $newToken = bin2hex(random_bytes(32));
            $user->setToken($newToken);
            $this->entityManager->flush();

            // Envoyer un e-mail avec le nouveau token
            $url = $request->getSchemeAndHttpHost() . $request->getBasePath() . '/statuts/' . $newToken;

            $email = (new Email())
                ->from('noreply@ac-guadeloupe.fr')
                ->to($user->getEmail())
                ->subject('Votre session a expiré - Nouveau lien de connexion')
                ->html('<p>Bonjour,</p><p>Votre session a expiré. Cliquez sur le lien suivant pour vous reconnecter : <a href="' . $url . '">' . $url . '</a></p>');

            $this->mailer->send($email);

            // Déconnecter l'utilisateur
            $session->clear();
            $response = new RedirectResponse('/testnewcomer/session-expired');
            $event->setResponse($response);
            return;
          
        }

        // Mettre à jour l'activité récente
        $session->set('last_activity', time());
    }
}
