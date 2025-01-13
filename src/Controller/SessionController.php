<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Classe\MonApplication;

class SessionController extends AbstractController
{
    #[Route('/keep-session-alive', name: 'keep_session_alive', methods: ['POST'])]
    public function keepSessionAlive(Request $request, SessionInterface $session): Response
    {
        $session->set('LAST_ACTIVITY', time());
        return new Response(null, Response::HTTP_OK);
    }

    #[Route('/session-expired', name: 'session_expired')]
public function sessionExpired(MonApplication $monApplication): Response
{
    return $this->render('session/session_expire.html.twig', [
        'message' => 'Votre session a expiré. Veuillez vérifier vos emails pour un nouveau lien de connexion.',
        'monApplication' => $monApplication,
    ]);
}



}
