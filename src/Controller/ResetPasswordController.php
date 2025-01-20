<?php
namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\PasswordResetToken;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; 
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResetPasswordController extends AbstractController
{
    private $timezone;

    public function __construct()
    {
        
        $this->timezone = new \DateTimeZone('America/Guadeloupe'); 
    }




    /**
 * @brief Gère la demande de réinitialisation du mot de passe.
 *
 * Cette méthode permet à un utilisateur de demander la réinitialisation de son mot de passe.
 * Si l'e-mail fourni correspond à un compte existant, un jeton de réinitialisation est généré
 * et un lien de réinitialisation est envoyé à l'utilisateur.
 *
 * @Route('/forgot-password', name='forgot_password')
 *
 * @param MonApplication $monApplication Informations sur l'application.
 * @param Request $request La requête HTTP contenant les données de soumission du formulaire.
 * @param UserRepository $userRepository Le dépôt pour accéder aux entités utilisateur.
 * @param EntityManagerInterface $em Gestionnaire d'entités Doctrine.
 * @param MailerInterface $mailer Service d'envoi d'e-mails.
 *
 * @return Response La page de demande de réinitialisation ou une redirection.
 *
 * @details
 * - Si l'adresse e-mail fournie ne correspond pas à un utilisateur, un message d'erreur est affiché.
 * - Si un jeton de réinitialisation valide existe déjà pour l'utilisateur, l'utilisateur est informé.
 * - Un nouveau jeton de réinitialisation est créé avec une durée de validité de 24 heures.
 * - Un lien unique contenant le jeton est envoyé par e-mail à l'utilisateur.
 *
 * @throws Exception Si une erreur survient lors de la génération du jeton ou de l'envoi de l'e-mail.
 *

 */



    #[Route('/forgot-password', name: 'forgot_password')]
public function forgotPassword(MonApplication $monApplication,
    Request $request,
    UserRepository $userRepository,
    EntityManagerInterface $em,
    MailerInterface $mailer
): Response {
    if ($request->isMethod('POST')) {
        $email = $request->request->get('email');
        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $this->addFlash('error', "L'adresse email n'existe pas dans notre base de données");
            return $this->redirectToRoute('forgot_password');


        } if ($user) {
            $tokenRepository = $em->getRepository(PasswordResetToken::class);
            $existingToken = $tokenRepository->findOneBy(['user' => $user]);
            if ($existingToken && $existingToken->isValid()) {
                
                $this->addFlash('error', 'Un lien de réinitialisation est déjà actif. Veuillez vérifier votre e-mail.');
                return $this->redirectToRoute('forgot_password');
            }
          
            $token = new PasswordResetToken();
            $token->setToken(Uuid::v4());
            $token->setExpiresAt(new \DateTime('24 hours'));
            $token->setUser($user);

            $em->persist($token);
            $em->flush();

           
            $url = $this->generateUrl('reset_password', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        

            $emailMessage = (new Email())
                ->from('noreply@ac-guadeloupe.fr')
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe')
                ->text('MDP Reset.')
                ->html('<p>Cliquez sur le lien pour réinitialiser votre mot de passe :</p><a href="' . $url . '">Réinitialiser mon mot de passe</a>');

            $mailer->send($emailMessage);

            $this->addFlash('success', 'Un e-mail de réinitialisation a été envoyé.');
        }

        return $this->redirectToRoute('forgot_password');
    }

    return $this->render('password/forgot_password.html.twig', [
        
        'monApplication' => $monApplication,
      
    ]);
}


/**
 * @brief Réinitialise le mot de passe d'un utilisateur à l'aide d'un jeton valide.
 *
 * Cette méthode permet à un utilisateur de réinitialiser son mot de passe en
 * fournissant un nouveau mot de passe via un formulaire, à condition de disposer
 * d'un jeton de réinitialisation valide.
 *
 * @Route('/reset-password/{token}', name='reset_password')
 *
 * @param string $token Jeton de réinitialisation du mot de passe.
 * @param UserPasswordHasherInterface $userPasswordHasher Service pour hacher les mots de passe.
 * @param MonApplication $monApplication Informations sur l'application.
 * @param Request $request La requête HTTP contenant les données de soumission du formulaire.
 * @param EntityManagerInterface $em Gestionnaire d'entités Doctrine.
 *
 * @return Response La page de réinitialisation du mot de passe ou une redirection.
 *
 * @details
 * - Vérifie si le jeton fourni est valide et non expiré.
 * - Supprime le jeton une fois qu'il a été utilisé pour éviter les réutilisations.
 * - Hache et met à jour le nouveau mot de passe pour l'utilisateur.
 * - Fournit des vérifications sur la force et la confirmation du mot de passe.
 *
 * @throws NotFoundHttpException Si le jeton ou l'utilisateur associé est introuvable.
 * @throws AccessDeniedException Si le jeton est expiré ou invalide.
 *
 
 */

#[Route('/reset-password/{token}', name: 'reset_password')]
public function resetPassword(
    string $token,
    UserPasswordHasherInterface $userPasswordHasher,
    MonApplication $monApplication,
    Request $request,
    EntityManagerInterface $em
   
): Response {
    $tokenRepository = $em->getRepository(PasswordResetToken::class);
    $resetToken = $tokenRepository->findOneBy(['token' => $token]);


    if (!$resetToken ) {
       
        $this->addFlash('error', 'Le lien de réinitialisation est invalide ou expiré.');
        return $this->redirectToRoute('forgot_password');
    }
    if (!$resetToken->isValid()) {
        $em->remove($resetToken);
        $em->flush();

        $this->addFlash('error', 'Le lien de réinitialisation est invalide ou expiré.');
        return $this->redirectToRoute('forgot_password');
    }

    $user = $resetToken->getUser();
    if (!$user) {
        $this->addFlash('error', 'Jeton invalide.');
        return $this->redirectToRoute('forgot_password');
    }

    if ($request->isMethod('POST')) {
        $newPassword = $request->request->get('password');
        $confirmPassword = $request->request->get('confirm_password');
        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe saisis ne correspondent pas.');
            return $this->redirectToRoute('reset_password', ['token' => $token]);
        }
        if (strlen($newPassword) < 6) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            return $this->redirectToRoute('reset_password', ['token' => $token]);
        }


        $user->setPassword($userPasswordHasher->hashPassword($user, $newPassword));


        $em->remove($resetToken);
        $em->flush();

        $this->addFlash('success', 'Votre mot de passe a été réinitialisé.');
        return $this->redirectToRoute('app_login');
    }

    return $this->render('password/reset_password.html.twig', [
        'token' => $token,
        'monApplication' => $monApplication,
    ]);
}
}