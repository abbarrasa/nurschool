<?php

namespace Nurschool\OAuth;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class Flow
{
    public function begin(SessionInterface $session, string $provider): string
    {
        $state = bin2hex(random_bytes(32));
        $session->set('oauth_attempt', ['provider' => $provider, 'state' => $state, 'expires' => time() + 600]);
        return $state;
    }

    public function consume(SessionInterface $session, string $provider, mixed $state): void
    {
        $attempt = $session->remove('oauth_attempt');
        if (!is_array($attempt) || ($attempt['provider'] ?? null) !== $provider
            || !is_string($state) || !is_string($attempt['state'] ?? null)
            || !hash_equals($attempt['state'], $state) || ($attempt['expires'] ?? 0) < time()) {
            throw new \RuntimeException('OAuth state is invalid or expired.');
        }
    }
}
