<?php

namespace App\Controller;

use App\Classe\MonApplication;
use App\Entity\User;
use App\Entity\Demandes;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Form\DemandeFormType;

class FormulaireController extends AbstractController
{
    #[Route('/formulaire', name: 'formulaire')]
    public function index(MonApplication $monApplication, Request $request, EntityManagerInterface $entityManager, HttpClientInterface $httpClient): Response
    {
        
        $apiUrl = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/services';
        $apiToken = 'b97b055g210125afb4c5f507dc823958ff18dfa56a12c7n12agch8db58e21767';
        $response = $httpClient->request('GET', $apiUrl, [
            'headers' => [
                'x-auth-token' => $apiToken,
                'Accept' => 'application/json',
            ],
        ]);
        $services = $response->toArray();
        $servicesDropdownData = $this->transformServicesForDropdown($services);

        // Créer une nouvelle instance de l'entité User
        // $user = $this->getUser();
        // $user = $security->getUser();
        // Créez une nouvelle instance de l'entité Demandes
        
        $apiDataSecond = null;
       

        // Créer le formulaire associé à la demande
        $form = $this->createForm(DemandeFormType::class, null, [
            'services' => $servicesDropdownData,
        ]);

        // Gérer la soumission du formulaire
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        // if ($form->isSubmitted() && $form->isValid()) {
            if ($form->isSubmitted() && $form->isValid()) {
                // Récupérer l'ID du service sélectionné
                $selectedServiceId = $form->get('selectedService')->getData();
                dump($selectedServiceId);
          
         
            $apiUrlSecond = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/valideur/' . $selectedServiceId;
            $responseSecond = $httpClient->request('GET', $apiUrlSecond, [
                'headers' => [
                    'x-auth-token' => $apiToken,  
                    'Accept' => 'application/json',
                ],
            ]);
    
                $apiDataSecond = $responseSecond->toArray();
                dump($apiDataSecond);
                $nomValideur = $apiDataSecond[0]['valideur'];
                dump($nomValideur);

            // Récupérer les données du formulaire
            $nom = $form->get('nom')->getData();
            $prenom = $form->get('prenom')->getData();
            $fonction = $form->get('fonction')->getData();

            // Affecter les données de l'utilisateur à l'entité User
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setFonction($fonction);
            $demande = new Demandes();
            // Affecter les données de l'utilisateur à l'entité Demandes
            $demande->setIDutilisateur($user);
            $demande->setDate(new \DateTime());
            $demande->setHeureSoumission(new \DateTime());
            $demande->setTitre('Demande d\'accès à un poste informatique');
            $demande->setStatuts('En attente'); // Définir le statut par défaut
             $demande->setUidValideur($nomValideur);

            // Persister les entités dans la base de données
            $entityManager->persist($user);
            $entityManager->persist($demande);
            $entityManager->flush();
                
            // Redirection vers une autre page après la soumission du formulaire
            return $this->redirectToRoute('home');
        }

        // Afficher le formulaire de demande
        return $this->render('formulaire/index.html.twig', [
            'form' => $form->createView(), // Transmettre le formulaire à la vue
            'monApplication' => $monApplication,
        ]);
    }

    #[Route('/modifier_demandes/{id}', name: 'modifier_demandes', methods: ['GET', 'POST'])]
    public function modifierDemande(Request $request, EntityManagerInterface $entityManager, $id, MonApplication $monApplication): Response
    {
        // Recherchez la demande par son ID
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
        // Vérifiez si la demande existe
        if (!$demande) {
            throw $this->createNotFoundException('La demande n\'existe pas.');
        }

        $user_ldap = $security->getUser();
        $user = $this->getUser();
        // if ($user) {
        $userData = [
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'fonction' => $user->getFonction(),
            // 'service' => $user->get s
        ];

        // Créez le formulaire pré-rempli avec les données de la demande
        $form = $this->createForm(DemandeFormType::class, null, ['data' => $userData]);
    
        // Gérez la soumission du formulaire
        $form->handleRequest($request);
    
        // Traitez la soumission du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            $nom = $form->get('nom')->getData();
            $prenom = $form->get('prenom')->getData();
            $fonction = $form->get('fonction')->getData();

            // Affecter les données de l'utilisateur à l'entité User
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setFonction($fonction);

            $demande->setIDutilisateur($user);
            $demande->setDate(new \DateTime());
            $demande->setHeureSoumission(new \DateTime());
            $demande->setTitre('Demande d\'accès à un poste informatique');
            $demande->setStatuts('En attente');
            
            $entityManager->flush();
    
            // Redirigez l'utilisateur vers une autre page après la modification
            return $this->redirectToRoute('statuts');
        // }
     } 
        // if ($user_ldap) {

        // }
    
        // Affichez le formulaire de modification
        return $this->render('formulaire/modifier_demandes.html.twig', [
            'form' => $form->createView(),
            'monApplication' => $monApplication,
        ]);
    }
    private function transformServicesForDropdown(array $services): array
    {
        $servicesDropdownData = [];
        foreach ($services as $service) {
            $servicesDropdownData[$service['service']] = $service['id_service'];
        }

        return $servicesDropdownData;
    }
    
}
?>
