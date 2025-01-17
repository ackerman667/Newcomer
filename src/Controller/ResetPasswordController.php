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
        // $em->remove($resetToken);
        // $em->flush();

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