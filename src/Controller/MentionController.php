<?php
/**
 * @file MentionController.php
 * Controller Mention de l'application
 * @author Olivier Mauréaux
 * @date 12/2023
 */
namespace App\Controller;

use App\Classe\MonApplication;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MentionController extends AbstractController
{
    /**
     * @Route("/mention", name="mention")
     */
    public function index()
    {
        // Créer une instance du client HTTP Symfony
        $client = HttpClient::create();

        // Définir l'URL de l'API que vous souhaitez interroger
        $url = $_SERVER["URLAPI"];

        // Définir le token API de type Bearer
        $token = $_SERVER["TOKENAPI"];

        // Envoyer la requête à l'API avec le token dans l'en-tête Authorization
        $response = $client->request('GET', $url, [
            'headers' => [
                // 'Authorization' => 'Bearer ' . $token,
                // 'auth_bearer' => $token,
                'x-auth-token' => $token,
                'Accept' => 'application/json', // Ajoutez d'autres en-têtes si nécessaire
            ],
        ]);

        try {
            // Récupérez le contenu de la réponse au format JSON
            
            $data = $response->toArray();
            // dd($data[0]);
            // Traitez les données de la réponse
            // ...

            // Retournez ou utilisez les données selon vos besoins
            // return $data;
        } 
        catch (ExceptionInterface $e) {
            // Gérez les erreurs de requête
            // ...

            // Retournez ou gérez les erreurs selon vos besoins
            // return $e->getMessage();
            // dd($e->getMessage());
            return $this->render('erreur/erreur.html.twig', [
                "module" =>'Mention',
                "message" => $e->getMessage()
            ]);
        }

        return $this->render('mention/mention.html.twig', [
            "data" =>$data
        ]);
    }
}
