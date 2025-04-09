<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppCustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;
    private UserRepository $userRepository;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator , UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;

    }


    /**
 * @brief Authentifie un utilisateur à partir des données de la requête.
 *
 * Cette méthode extrait l'email, le mot de passe et le jeton CSRF de la requête,
 * vérifie les informations fournies, et construit un objet `Passport` pour l'authentification.
 *
 * @param Request $request La requête HTTP contenant les informations de connexion.
 *
 * @return Passport Un objet Passport contenant les informations d'authentification.
 *
 * @throws BadCredentialsException Si l'email n'existe pas, le mot de passe est incorrect,
 * ou si l'utilisateur tente de se connecter avec une adresse académique.
 *
 * @details
 * - L'email doit être valide et ne pas appartenir au domaine académique.
 * - Le mot de passe est vérifié par rapport au hachage stocké.
 * - Un badge CSRF est ajouté pour protéger l'authentification.
 *
 */


    public function authenticate(Request $request): Passport
    {
        // dd($request->request->all());
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $csrfToken = $request->request->get('_csrf_token');
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (str_ends_with($email, '@ac-guadeloupe.fr')) {
            throw new BadCredentialsException('Si vous avez une adresse se académique (se terminant par ac-guadeloupe.fr) rendez vous sur le portail pour acceder a l\'applciation.');
        }
        if (!$user) {
            throw new BadCredentialsException('Cet email n’existe pas.');
        }

        // Vérifiez le mot de passe
        if (!password_verify($password, $user->getPassword())) {
            throw new BadCredentialsException('Mot de passe incorrect.');
        }

        if (!$user->isCompteActif()) {
            throw new BadCredentialsException('Votre compte n\'est pas encore activé. Veuillez vérifier votre boîte mail.');
        }
        


        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
            ]
        );
    }



    /**
 * @brief Gère la redirection après une authentification réussie.
 *
 * Cette méthode redirige l'utilisateur vers une page spécifique après une connexion réussie.
 *
 * @param Request $request La requête HTTP.
 * @param TokenInterface $token Le jeton d'authentification généré.
 * @param string $firewallName Le nom du firewall utilisé pour l'authentification.
 *
 * @return Response Une réponse de redirection vers la route `demande_externe`.
 *
 * @details
 * 
 * - il est redirigé par défaut vers `demande_externe`.
 */

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
        //     return new RedirectResponse($targetPath);
        // }

        return new RedirectResponse($this->urlGenerator->generate('demande_externe'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }


    /**
 * @brief Gère les échecs d'authentification.
 *
 * Cette méthode capture les exceptions d'authentification, affiche un message d'erreur
 * à l'utilisateur, et redirige vers la page de connexion.
 *
 * @param Request $request La requête HTTP contenant les informations de connexion.
 * @param AuthenticationException $exception L'exception générée lors de l'échec.
 *
 * @return Response Une réponse de redirection vers le formulaire de connexion.
 *
 * @details
 * - L'exception est analysée pour extraire un message d'erreur.
 * - Un message flash est ajouté à la session pour informer l'utilisateur.
 */

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $message = $exception->getMessage();
        $request->getSession()->getFlashBag()->add('error', $message);
    
        return new RedirectResponse($this->getLoginUrl($request));
    }

   
}
