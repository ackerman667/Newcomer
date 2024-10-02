<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TestValideurController extends AbstractController
{
    #[Route('/test/valideur', name: 'test_valideur')]
    public function index(Request $request, HttpClientInterface $httpClient): Response
    {
        $result = null;
        $uid = $request->get('uid'); // Récupérer l'UID soumis dans le formulaire
        
        if ($uid) {
            // URL de l'API avec l'UID entré par l'utilisateur
            $apiUrl = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/valideur/' . $uid;
            $apiToken = 'b97b055g210125afb4c5f507dc823958ff18dfa56a12c7n12agch8db58e21767';

            // Envoi de la requête GET à l'API
            $response = $httpClient->request('GET', $apiUrl, [
                'headers' => [
                    'x-auth-token' => $apiToken,
                    'Accept' => 'application/json',
                ],
            ]);

            // Récupération de la réponse JSON
            $data = $response->toArray();
            dump($data);

            // Vérification si l'UID est un valideur
            if (isset($data[0]['service']) && $data[0]['service'] !== "Pas valideur") {
                $result = "L'UID {$uid} est valideur du service : " . $data[0]['service'];
            } else {
                $result = "L'UID {$uid} n'est pas un valideur.";
            }
            
        }

        return $this->render('test/valideur.html.twig', [
            'result' => $result,
        ]);
    }
}
