<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Demandes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Classe\MonApplication;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Ressources;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Security\UserInformation;
use App\Entity\HistoriqueDemande;

use Symfony\Component\Security\Core\Security;


class StatutController extends AbstractController
{
    private $security;
    private $timezone;

    public function __construct(Security $security)
    {
        $this->security = $security;
        $this->timezone = new \DateTimeZone('America/Guadeloupe');
    }

    #[Route('formulaireldap/statuts', name: 'statuts_token_ldap')]
    public function index(MonApplication $monApplication, EntityManagerInterface $entityManager): Response
    {

        $user_ldap = $this->security->getUser();
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user_ldap);
        //  dump($infos_user);
        $uid_ldap = $infos_user['uid'];
        $user_bdd= $entityManager->getRepository(User::class)->findBy(['uid' => $uid_ldap]);

        $demandes = $entityManager->getRepository(Demandes::class)->findBy(['IDutilisateur' => $user_bdd]);

  
        $query = $entityManager->createQuery(
            'SELECT u
            FROM App\Entity\User u
            WHERE u.uid = :uid
            AND u.email LIKE :email'
        )->setParameters([
            'uid' => $uid_ldap,
            'email' => '%@ac-guadeloupe.fr'
        ]);
        
        $user= $query->getOneOrNullResult();
        
        // $user = $demande->getIDutilisateur();
        // $demandes = $entityManager->getRepository(Demandes::class)->findBy(['IDutilisateur' => $user]);

        return $this->render('statuts/token_ldap.html.twig', [
            'demandes' => $demandes,
            'monApplication' => $monApplication,
            'user' => $user,
        ]);
    }

    #[Route('formulaireldap/demande/consult/{token}', name: 'demande_consult_ldap')]
    public function consult(MonApplication $monApplication, $token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
    
        // Faire la distinction si la demande est pour une autre personne ou non
        if ($demande->isAutrePersonne()) {
            // Récupérer les informations de l'autre personne à partir du JSON
            $infos_personne = $demande->getInfosPersonne();
            if (!is_array($infos_personne)) {
                $infos_personne = json_decode($infos_personne, true) ?? [];
            }
    
            // Formatage de la date de naissance
            if (isset($infos_personne['date_de_naissance']) && is_array($infos_personne['date_de_naissance'])) {
                $dateString = $infos_personne['date_de_naissance']['date'];
                $dateNaissance = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $dateString);
                $infos_personne['date_de_naissance'] = $dateNaissance;
            }
    
            $user = $infos_personne; // Utiliser les infos du JSON pour l'affichage
        } else {
            // Si la demande n'est pas pour une autre personne, utiliser l'utilisateur lié à la demande
            $user = $demande->getIDutilisateur();
        }
    
        return $this->render('consult/visualiser.html.twig', [
            'demande' => $demande,
            'user' => $user,
            'monApplication' => $monApplication,
            'ressources' => $ressources,
        ]);
    }
    



    #[Route('formulaireldap/demande/pdf/{token}', name: 'demande_pdf_ldap')]
    public function generatePdf($token, EntityManagerInterface $entityManager): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->findOneBy(['token' => $token]);
    
        // Vérifier si la demande est valide
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
        $ressources = $entityManager->getRepository(Ressources::class)->findOneBy(['demande' => $demande]);
    
        // Vérifier si la demande est faite pour une autre personne
        if ($demande->isAutrePersonne()) {
            // Récupérer les informations à partir du JSON
            $infosPersonne = $demande->getInfosPersonne();
    
            // Vérifier que le contenu est une chaîne JSON avant d'appeler json_decode
            if (is_string($infosPersonne)) {
                $userInfos = json_decode($infosPersonne, true);
            } else {
                // Si ce n'est pas une chaîne, on considère que c'est déjà un tableau
                $userInfos = $infosPersonne;
            }
    
            // Convertir la date de naissance si elle est présente et au bon format
            if (!empty($userInfos['date_de_naissance']) && is_array($userInfos['date_de_naissance'])) {
                $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d H:i:s.u', $userInfos['date_de_naissance']['date']);
            } elseif (!empty($userInfos['date_de_naissance']) && is_string($userInfos['date_de_naissance'])) {
                // Si la date est une chaîne, essayez de la convertir
                $userInfos['date_de_naissance'] = \DateTime::createFromFormat('Y-m-d', $userInfos['date_de_naissance']);
            }
        } else {
            // Récupérer les informations de l'utilisateur lié
            $user = $demande->getIDutilisateur();
            $userInfos = [
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'email' => $user->getEmail(),
                'date_de_naissance' => $user->getDateDeNaissance(),
                'fonction' => $user->getFonction(),
                'statut' => $user->getStatutPersonne(),
                'date_debut' => $user->getDateDebut(),
                'date_fin' => $user->getDateFin(),
            ];
        }
    

        $imagePath = 'C:\Users\nbarbeu\newcomer\public\interfaceappli\css\images\logoaca\academie.png'; 
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageSrc = 'data:image/png;base64,' . $imageData;
    
        // Configurer Dompdf selon vos besoins
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
    
        // Récupérer le contenu HTML de votre template
        $html = $this->renderView('consult/pdf.html.twig', [
            'demande' => $demande,
            'user' => $userInfos,
            'ressources' => $ressources,
            'imageSrc' => $imageSrc,
        ]);
    
        // Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);
    
        // Définir le format du papier et l'orientation
        $dompdf->setPaper('A4', 'portrait');
    
        // Rendre le PDF
        $dompdf->render();
    
        // Envoyer le PDF au navigateur
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="demande.pdf"',
        ]);
    }
    
    #[Route('/formulaireldap/nouvelle_demande', name: 'nouvelle_demande_ldap')]
public function nouvelleDemande(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
    // Réinitialiser les données de la session pour démarrer une nouvelle demande
    $session->remove('form_data');
    $session->remove('demande_id');

    $session->set('nouvelle_demande', true);
    

    return $this->redirectToRoute('formulaireldap_etape1');
}

#[Route('/formulaireldap/supprimer/{id}', name: 'formulaireldap_supprimer')]
    public function supprimerDemande($id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
    
     
        $historiques = $entityManager->getRepository(HistoriqueDemande::class)->findBy(['demande' => $demande]);
        foreach ($historiques as $historique) {
            $entityManager->remove($historique);
        }
    
 
        $ressources = $entityManager->getRepository(Ressources::class)->findBy(['demande' => $demande]);
        foreach ($ressources as $ressource) {
            $entityManager->remove($ressource);
        }
    
        $entityManager->remove($demande);
        $entityManager->flush();
    
        $this->addFlash('success', 'La demande a été supprimée avec succès.');
    
        return $this->redirectToRoute('statuts_token_ldap');
    }






    #[Route('/formulaireldap/modifier/{id}', name: 'modifier_demandesldap')]
    public function modifierDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $user = $this->security->getUser();
        $userInformation = new UserInformation();
        $infos_user = $userInformation->getUserInformation($user);
        $email_utilisateur= $infos_user['mail'];
        $user1 = $entityManager->getRepository(User::class)->findOneBy(['email' => $email_utilisateur]);
        $token = $demande->getToken();

        // Pré-remplir les données pour le formulaire
        $data = [
            'nom' => $user1->getNom(),
            'prenom' => $user1->getPrenom(),
            'email' => $user1->getEmail(),
            'date_de_naissance' => $user1->getDateDeNaissance(),
            'fonction' => $user1->getFonction(),
            'replace_someone' => $demande->isRemplacant() ? 'oui' : 'non',
            'remplacement_nom' => $demande->getNomRemplacant(),
            'remplacement_prenom' => $demande->getPrenomRemplacant(),
            'telephone_avant_service' => $demande->getTelephoneRemplacant(),
            'parti_rectorat' => $demande->isDepart(),
            'nouvelle_affectation_service' => $demande->getAffectationRemplacant(),
            'statut' => $user1->getStatutPersonne(),
            'fonction' => $user1->getFonction(),
            'date_debut_contrat' => $user1->getDateDebut(),
            'date_fin_contrat' => $user1->getDateFin(),
            'missions' => $demande->getMissions(),
        ];

        $session->set('form_data', $data);
        $session->set('demande_id', $id);

        return $this->redirectToRoute('formulaireldap_etape1', ['id' => $id]);
    }


    #[Route('/formulaireldap/valider/{id}', name: 'valider_demandesldap')]
    public function validerDemande(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, $id): Response
    {
        $demande = $entityManager->getRepository(Demandes::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée.');
        }
        $demande->setStatuts('En attente');
        $entityManager->persist($demande);
        $historique = new HistoriqueDemande();
        $historique->setDemande($demande);
                $historique->setStatut('Envoyée');
                
                $historique->setDate(new \DateTime('now', $this->timezone));
                $historique->setStatutOperation('Envoie de la demande');

                $entityManager->persist($historique);
        $entityManager->flush();
        $token = $demande->getToken();
    

        return $this->redirectToRoute('statuts_token_ldap');
    }





}
