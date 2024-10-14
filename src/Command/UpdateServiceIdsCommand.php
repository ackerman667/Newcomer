<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UpdateServiceIdsCommand extends Command
{
    protected static $defaultName = 'app:update-service-ids';
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        parent::__construct();
        $this->httpClient = $httpClient;
    }

    protected function configure()
    {
        $this
            ->setDescription('Updates the SERVICE_IDS in the .env.local file with services without parents.')
            ->setHelp('This command allows you to update the SERVICE_IDS variable in the .env.local file...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        $services = $response->toArray();

        // Utilisation de la méthode buildTree pour structurer les services
        $servicesTree = $this->buildTree($services);

        // Extraire les services sans parent (ceux à la racine de l'arbre)
        $servicesWithoutParent = array_map(function ($service) {
            return $service['id_service'];
        }, $servicesTree);

        // Convertir les IDs en chaîne de caractères
        $idsAsString = implode(',', $servicesWithoutParent);

        // Mettre à jour la variable dans .env.local
        $envFile = $this->getApplication()->getKernel()->getProjectDir().'/.env.local';
        $this->updateEnvVariable($envFile, $idsAsString);

        $output->writeln('SERVICE_IDS updated successfully!');

        return Command::SUCCESS;
    }

    private function updateEnvVariable($envFile, $idsAsString)
    {
        $envContent = file_get_contents($envFile);

        if (strpos($envContent, 'SERVICE_IDS=') !== false) {
            $envContent = preg_replace('/SERVICE_IDS=.*/', 'SERVICE_IDS="'.$idsAsString.'"', $envContent);
        } else {
            $envContent .= PHP_EOL.'SERVICE_IDS="'.$idsAsString.'"';
        }

        file_put_contents($envFile, $envContent);
    }

    private function buildTree(array &$services, $parentId = 0): array
    {
        $branch = [];
        foreach ($services as &$service) {
            if ($service['pere'] == $parentId) {
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
