<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Core\Security;

class UserRoleChecker
{
    private $httpClient;
    private $security;
    private $apiToken = 'b97b055g210125afb4c5f507dc823958ff18dfa56a12c7n12agch8db58e21767';
    private $apiUrl = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/valideur/';

    public function __construct(HttpClientInterface $httpClient, Security $security)
    {
        $this->httpClient = $httpClient;
        $this->security = $security;
    }

    /**
     * Vérifie si l'utilisateur connecté est un valideur.
     */
    public function isUserValideur(): bool
    {
        $user = $this->security->getUser();
        if (!$user) {
            return false; // Utilisateur non connecté
        }

        $uid = $user->getUid(); // Récupérer l'UID de l'utilisateur connecté
        $response = $this->httpClient->request('GET', $this->apiUrl . $uid, [
            'headers' => [
                'x-auth-token' => $this->apiToken,
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray();

        // Vérifier si l'UID est un valideur ou non
        return isset($data[0]['service']) && $data[0]['service'] !== "Pas valideur";
    }
}
