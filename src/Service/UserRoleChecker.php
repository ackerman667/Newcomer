<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\SecurityBundle\Security;


class UserRoleChecker
{
    private $httpClient;
    private $security;
    private $apiToken;
    private $apiUrl = 'http://import-data.in.ac-guadeloupe.fr/Febex_API/api/valideur/';

    public function __construct(HttpClientInterface $httpClient, Security $security)
    {
        $this->httpClient = $httpClient;
        $this->security = $security;
        $this->apiToken = $_ENV['API_Token'];

    }

   /**
 * @brief Vérifie si l'utilisateur connecté est un valideur.
 *
 * Cette méthode interroge une API externe pour déterminer si l'utilisateur actuellement connecté
 * dispose des droits de valideur. L'API est sécurisée par un token d'authentification et retourne
 * une réponse JSON qui est analysée pour vérifier le rôle de l'utilisateur.
 *
 * @return bool Retourne `true` si l'utilisateur est un valideur, `false` sinon.
 *
 * @details
 * - Si aucun utilisateur n'est connecté, la méthode retourne immédiatement `false`.
 * - La méthode envoie une requête GET vers une URL d'API spécifique, composée dynamiquement à partir
 *   de l'UID de l'utilisateur connecté.
 * - Un token d'authentification (`x-auth-token`) est ajouté dans les en-têtes de la requête pour sécuriser l'accès.
 * - La réponse de l'API est analysée pour vérifier si le champ `service` contient autre chose que "Pas valideur".
 *
 * @throws TransportExceptionInterface Si la requête HTTP échoue.
 * @throws DecodingExceptionInterface Si la réponse JSON est invalide.
 * @throws RedirectionExceptionInterface Si une redirection inattendue se produit.
 * @throws ClientExceptionInterface Si une erreur client survient.
 * @throws ServerExceptionInterface Si une erreur serveur survient.
 *

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
