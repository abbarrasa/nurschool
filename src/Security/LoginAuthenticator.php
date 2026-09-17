<?php

namespace Nurschool\Security;

use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnsupportedMediaTypeHttpException};
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class LoginAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'api_login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        // Require JSON so cross-origin HTML forms cannot submit credentials.
        if ($request->getContentTypeFormat() !== 'json') {
            throw new UnsupportedMediaTypeHttpException('Envía las credenciales como application/json.');
        }
        $data = $request->toArray();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        if (!is_string($email) || !is_string($password) || $password === ''
            || strlen($password) > 4096 || strlen($email) > 180
            || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Introduce un email válido y una contraseña.');
        }
        return new Passport(new UserBadge($email), new PasswordCredentials($password));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new JsonResponse(['email' => $token->getUserIdentifier()], 200, ['Cache-Control' => 'no-store']);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['message' => 'Email o contraseña incorrectos.'], 401, ['Cache-Control' => 'no-store']);
    }
}
