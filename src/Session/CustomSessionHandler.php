<?php

namespace App\Session;

use SessionHandlerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class CustomSessionHandler implements SessionHandlerInterface
{
    private $savePath;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function open($savePath, $sessionName)
    {
        $this->savePath = $savePath;
        // Vérifier si le répertoire existe, sinon le créer
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0777);
        }
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        // Lire les données de session
        return (string)@file_get_contents("$this->savePath/sess_$id");
    }

    public function write($id, $data)
    {
        // Écrire les données dans un fichier de session
        return file_put_contents("$this->savePath/sess_$id", $data) === false ? false : true;
    }

    public function destroy($sessionId)
{
    // Supprimer le fichier de session lorsqu'il est détruit manuellement
    $file = "$this->savePath/sess_$sessionId";
    if (file_exists($file)) {
        unlink($file); // Supprime le fichier de session
    }

    return true;
}


    public function gc($maxlifetime)
{
    foreach (glob("$this->savePath/sess_*") as $file) {
        // Vérifie si la session a expiré
        if (filemtime($file) + $maxlifetime < time() && file_exists($file)) {
            // Lire les données de session pour vérifier si le token doit être régénéré
            $sessionData = file_get_contents($file);
            $sessionArray = unserialize($sessionData);

            // Vérifie si 'externe_token' est présent
            if (isset($sessionArray['externe_token'])) {
                $externalToken = $sessionArray['externe_token'];

                // Cherche l'utilisateur correspondant au token
                $user = $this->entityManager
                    ->getRepository(User::class)
                    ->findOneBy(['token' => $externalToken]);

                if ($user) {
                    // Génère un nouveau token
                    $newToken = bin2hex(random_bytes(32));

                    // Mets à jour l'utilisateur avec le nouveau token
                    $user->setToken($newToken);

                    // Sauvegarde les changements dans la base de données
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }
            }

            // Supprime le fichier de session expiré
            unlink($file);
        }
    }

    return true;
}

}
