<?php

namespace Nurschool\Security;

use Nurschool\OAuth\{AccountResolver, Flow, ProviderClient};
use Symfony\Component\HttpFoundation\{RedirectResponse, Request, Response};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\{Passport, SelfValidatingPassport};

final class SocialAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly Flow $flow,
        private readonly ProviderClient $client,
        private readonly AccountResolver $accounts,
        private readonly UrlGeneratorInterface $urls
    ){
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'api_oauth_callback' && $request->isMethod('GET');
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $provider = (string) $request->attributes->get('provider');
            $query = $request->query->all();
            $this->flow->consume($request->getSession(), $provider, $query['state'] ?? null);
            $code = $query['code'] ?? null;
            if (isset($query['error']) || !is_string($code) || $code === '' || strlen($code) > 4096) {
                throw new \RuntimeException('OAuth authorization was denied or omitted.');
            }
            $identity = $this->client->identity($provider, $code);
            $user = $this->accounts->resolve($provider, $identity['subject'], $identity['email']);
            return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
        } catch (\Exception) {
            // Do not expose provider responses, tokens, or account existence to the browser.
            throw new AuthenticationException('Social authentication failed.');
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return $this->redirect('nurschool_home');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->redirect('nurschool_login', ['oauth_error' => '1']);
    }

    /** @param array<string, string> $parameters */
    private function redirect(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, $parameters), headers: ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }
}
