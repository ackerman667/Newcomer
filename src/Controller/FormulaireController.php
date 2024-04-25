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

        $user = $this->getUser();
        
        $apiDataSecond = null;
       

       
        $form = $this->createForm(DemandeFormType::class, null, [
            'services' => $servicesDropdownData,
            'data' => [
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'date_de_naissance' => $user->getDateDeNaissance(),
            ],
        ]);

        $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                
            
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

            
            $nom = $form->get('nom')->getData();
            $prenom = $form->get('prenom')->getData();
            $fonction = $form->get('fonction')->getData();

            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setFonction($fonction);
            $demande = new Demandes();
        
            $demande->setIDutilisateur($user);
            $demande->setDate(new \DateTime());
            $demande->setHeureSoumission(new \DateTime());
            $demande->setTitre('Demande d\'accès à un poste informatique');
            $demande->setStatuts('En attente'); 
             $demande->setUidValideur($nomValideur);
            
             $html = $this->renderView('formulaire/pdf_template.html.twig', [
                
                'nom' => $nom,
                'prenom' => $prenom,
                'fonction' => $fonction,
                
            ]);
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
        
            // Convertir le contenu PDF en base64
            $pdfContent = $dompdf->output();
            // $pdfBase64 = base64_encode($pdfContent);
        
            // Enregistrer le PDF dans la base de données
        
            $demande->setPdf($pdfContent);
            
            // $pdfBase64 = base64_encode($pdfContent);
            // $demande->setPdf($pdfBase64);
            $entityManager->persist($user);
            $entityManager->persist($demande);
            $entityManager->flush();
            $response = new Response($dompdf->output());
            $response->headers->set('Content-Type', 'application/pdf');
            return $response;
            return $this->redirectToRoute('home');

            
        }
        
       
        return $this->render('formulaire/index.html.twig', [
            'form' => $form->createView(), 
            'monApplication' => $monApplication,
        ]);
    }

    #[Route('/modifier_demandes/{id}', name: 'modifier_demandes', methods: ['GET', 'POST'])]
    public function modifierDemande(Request $request, EntityManagerInterface $entityManager, $id, MonApplication $monApplication): Response
    {
        
        $demande = $entityManager->getRepository(Demandes::class)->find($id);
    
       
        if (!$demande) {
            throw $this->createNotFoundException('La demande n\'existe pas.');
        }

       
        $user = $this->getUser();
        
        $userData = [
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'fonction' => $user->getFonction(),
            'date_de_naissance' => $user->getDateDeNaissance(),
            
        ];

        
        $form = $this->createForm(DemandeFormType::class, null, ['data' => $userData]);
    
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nom = $form->get('nom')->getData();
            $prenom = $form->get('prenom')->getData();
            $fonction = $form->get('fonction')->getData();

         
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setFonction($fonction);

            $demande->setIDutilisateur($user);
            $demande->setDate(new \DateTime());
            $demande->setHeureSoumission(new \DateTime());
            $demande->setTitre('Demande d\'accès à un poste informatique');
            $demande->setStatuts('En attente');
            
            $entityManager->flush();
    
           
            return $this->redirectToRoute('statuts');
       
     } 
       
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
