<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ServiceController extends AbstractController
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    #[Route('/services-without-parent', name: 'services_without_parent')]
    public function getServicesWithoutParent(): Response
    {
        // Appel à l'API pour récupérer les services
        $apiUrl = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/services';
        $apiToken = 'b97b055g210125afb4c5f507dc823958ff18dfa56a12c7n12agch8db58e21767';

        // Appel de l'API
        $response = $this->httpClient->request('GET', $apiUrl, [
            'headers' => [
                'x-auth-token' => $apiToken,
                'Accept' => 'application/json',
            ],
        ]);

        // Convertir la réponse JSON en tableau
        $services = $response->toArray();

        // Utilisation de la méthode buildTree pour structurer les services
        $servicesTree = $this->buildTree($services);

        // Extraire les services sans parent (ceux à la racine de l'arbre)
        $servicesWithoutParent = array_map(function ($service) {
            return $service['id_service'];
        }, $servicesTree);

        // Retourner les IDs dans une réponse JSON
        return new JsonResponse([
            'service_ids' => $servicesWithoutParent,
        ]);
    }

    /**
     * Construire un arbre de services
     */
    private function buildTree(array &$services, $parentId = 0): array
    {
        $branch = [];
        foreach ($services as &$service) {
            // Si le service est à la racine (pas de parent)
            if ($service['pere'] == $parentId) {
                // Récursion pour récupérer les enfants du service
                $children = $this->buildTree($services, $service['id_service']);
                if ($children) {
                    $service['children'] = $children;
                }
                $branch[] = $service;
            }
        }
        return $branch;
    }
}
