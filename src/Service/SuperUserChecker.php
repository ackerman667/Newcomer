<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SuperUserChecker
{
    private array $superUsers;
    private Security $security;

    public function __construct(ParameterBagInterface $params, Security $security)
    {
        $this->superUsers = explode(',', $params->get('SUPER_USERS')); // Récupération des super utilisateurs
        $this->security = $security;
    }

    /**
     * Vérifie si l'utilisateur actuel est un super utilisateur.
     *
     * @return bool
     */
    public function isSuperUser(): bool
    {
        $user = $this->security->getUser();

        if (!$user) {
            return false; // Aucun utilisateur connecté
        }

        return in_array($user->getUid(), $this->superUsers, true);
    }
}
