<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function loadUserByUsername($username)
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $username): UserInterface
    {
        return $this->cache->get('user_' . $username, function (ItemInterface $item) use ($username) {
            $item->expiresAfter(3600); // Cache expiration time (e.g., 1 hour)
            
            $roleuser = new UserInformation();
            $utilisateur = $roleuser->getUserInformation();
            $TypeAppliDomainTheme = new TypeAppliDomainTheme();
            
            if ($utilisateur) {
                return new User($_SERVER['HTTP_CT_REMOTE_USER'], $TypeAppliDomainTheme, $utilisateur);
            } else {
                return new User($_SERVER['HTTP_CT_REMOTE_USER'], $TypeAppliDomainTheme);
            }
        });
    }

    // public function refreshUser(UserInterface $user): UserInterface
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->cache->get('user_' . $user->getUid(), function (ItemInterface $item) use ($user) {
            $item->expiresAfter(3600); // Cache expiration time (e.g., 1 hour)
            
            $roleuser = new UserInformation();
            $utilisateur = $roleuser->getUserInformation();
            $TypeAppliDomainTheme = new TypeAppliDomainTheme();
            
            if ($utilisateur) {
                return new User($_SERVER['HTTP_CT_REMOTE_USER'], $TypeAppliDomainTheme, $utilisateur);
            } else {
                return new User($_SERVER['HTTP_CT_REMOTE_USER'], $TypeAppliDomainTheme);
            }
        });
    }

    public function supportsClass($class): bool
    {
        return User::class === $class;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newEncodedPassword): void
    {
        // TODO: when encoded passwords are in use, this method should:
        // 1. persist the new password in the user storage
        // 2. update the $user object with $user->setPassword($newEncodedPassword);
    }
}
