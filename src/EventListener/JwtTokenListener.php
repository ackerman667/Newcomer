<?php



namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class JwtTokenListener
{
    private $jwtManager;

    public function __construct(JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        $jwt = $request->cookies->get('JWT_TOKEN');

        if (!$jwt) {
            throw new UnauthorizedHttpException('JWT token not found.');
        } else {
            $token = $this->jwtManager->parse($jwt);
          
            $request->attributes->set('_jwt_token', $token);
        }
    }
}


       
    

