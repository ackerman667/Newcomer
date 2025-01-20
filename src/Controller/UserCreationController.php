<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserCreationFormType;
use App\Classe\MonApplication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\UserRepository;
use Symfony\Component\Mime\Email;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserCreationController extends AbstractController
{
    public function __construct(private UrlGeneratorInterface $urlGenerator, private UserRepository $userRepository, private Security $security
    ) {
        $this->security = $security;   
        $this->timezone = new \DateTimeZone('America/Guadeloupe');
        


    }



    /**
 * @brief Crée un nouvel utilisateur externe.
 *
 * Cette méthode permet de créer un compte utilisateur pour les utilisateurs externes
 * en fournissant un formulaire de saisie. Les informations sont vérifiées, et un compte
 * est créé si toutes les conditions sont respectées.
 *
 * @Route('/create-user', name='user_creation')
 *
 * @param UserPasswordHasherInterface $userPasswordHasher Service pour hacher les mots de passe.
 * @param MonApplication $monApplication Informations sur l'application.
 * @param Request $request La requête HTTP contenant les données du formulaire.
 * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
 * @param MailerInterface $mailer Service d'envoi d'e-mails (non utilisé dans cette méthode, mais disponible).
 *
 * @return Response La page de création d'utilisateur ou une redirection.
 *
 * @details
 * - Si l'utilisateur est déjà connecté, il est redirigé vers `demande_externe`.
 * - Vérifie si l'adresse e-mail est déjà utilisée ou si elle appartient au domaine académique.
 * - Valide la correspondance entre les mots de passe saisis.
 * - Crée un utilisateur avec des informations personnelles et un mot de passe haché.
 * - Enregistre l'utilisateur dans la base de données et effectue une connexion automatique.
 *
 * @throws Exception Si une erreur survient lors de la génération du token ou de l'enregistrement.
 *
 */

    #[Route('/create-user', name: 'user_creation')]
    public function createUser(UserPasswordHasherInterface $userPasswordHasher, MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('demande_externe');
       }
        $email = $request->query->get('email', '');
        
        $form = $this->createForm(UserCreationFormType::class, ['email' => $email]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email_user = $form->get('email')->getData();

    
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email_user]);

        if ($existingUser) {
            
            $this->addFlash('error', 'L\'adresse e-mail est déjà utilisée.');

            return $this->render('user_creation/index.html.twig', [
                'form' => $form->createView(),
                'monApplication' => $monApplication,
            ]);
        }
        if (str_ends_with($email_user, '@ac-guadeloupe.fr')) {
            // Ajoutez un message flash de type warning
            $this->addFlash('error', 'Vous avez entré une adresse académique. Si vous disposez d\'un compte académique, rendez-vous sur le portail .');
            return $this->render('user_creation/index.html.twig', [
                'form' => $form->createView(),
                'monApplication' => $monApplication,
            ]);
        }
    
            $user = new User();
            $user->setCompteActif(false);
            $nom = $form->get('nom')->getData();
            $prenom = $form->get('prenom')->getData();
            $fonction = $form->get('fonction')->getData();
            $email_user = $form->get('email')->getData();
            $date_de_naissance = $form->get('date_de_naissance')->getData();
            $password = $form->get('password')->getData();
$confirmPassword = $form->get('confirm_password')->getData();

            
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setFonction($fonction);
            $user->setDateDeNaissance($date_de_naissance);
            $user->setFonction($fonction);
            $user->setEmail($email_user);
            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
        
                return $this->render('user_creation/index.html.twig', [
                    'form' => $form->createView(),
                    'monApplication' => $monApplication,
                ]);
            }
            $user->setPassword( $userPasswordHasher->hashPassword(  $user, $form->get('password')->getData()
            )
);

    //         $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    // $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_DB_USER']);

            $token = bin2hex(random_bytes(32));
            $expiration = new \DateTimeImmutable('+24 hours');
            $user->setToken($token);
            $user->setTokenExpiration($expiration);
            $user->setProvenance('externe');
            $entityManager->persist($user);
            $entityManager->flush();

            $this->security->login($user);

            // $this->addFlash('success', 'Votre compte a bien été créé. Veuillez l\'activer par mail.');

            return $this->redirectToRoute('demande_externe');
        }

        return $this->render('user_creation/index.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,

        ]);
    }
    #[Route('/create-user/confirmation', name: 'user_creation_confirmation')]
    public function userCreationConfirmation(MonApplication $monApplication): Response
    {
        return $this->render('user_creation/confirmation.html.twig', [
            'monApplication' => $monApplication,
        ]);
    }
}
