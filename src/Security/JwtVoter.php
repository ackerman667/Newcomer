<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class JwtVoter implements VoterInterface
{
    // public const PRIORITY = 100;
    private $requestStack;
    private $jwtManager;

    public function __construct(JWTTokenManagerInterface $jwtManager, RequestStack $requestStack)
    {
        $this->jwtManager = $jwtManager;
        $this->requestStack = $requestStack;
    }
     
   

    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        
        $request = $this->requestStack->getCurrentRequest();
        $Token = $request->cookies->get('JWT_TOKEN');
      
        //dump($jwtToken);


        if (!$Token) {
            return VoterInterface::ACCESS_ABSTAIN;
        } else {

            $jwtToken = $this->jwtManager->parse($Token);
        
            $userRoles = $jwtToken['roles'];
            dump($userRoles);


dump($attributes);
    $requiredRoles = $attributes ?? [];
    dump($requiredRoles);

    foreach ($requiredRoles as $requiredRole) {
        
        if (in_array($requiredRole, $userRoles)) {
            return VoterInterface::ACCESS_GRANTED;
        }
    }

    return VoterInterface::ACCESS_DENIED;
}
}
}