<?php

namespace Nurschool\Tests\Unit;

use Nurschool\OAuth\Flow;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\{Session, Storage\MockArraySessionStorage};

final class OAuthFlowTest extends TestCase
{
    public function testStateCanOnlyBeConsumedOnce(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $flow = new Flow();
        $state = $flow->begin($session, 'google');
        $flow->consume($session, 'google', $state);
        $this->expectException(\RuntimeException::class);
        $flow->consume($session, 'google', $state);
    }

    /** @dataProvider invalidAttempts */
    public function testRejectsInvalidAttempts(string $provider, int $expires, string $state): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('oauth_attempt', ['provider' => 'google', 'state' => 'expected', 'expires' => $expires]);
        $this->expectException(\RuntimeException::class);
        (new Flow())->consume($session, $provider, $state);
    }

    public static function invalidAttempts(): iterable
    {
        yield ['facebook', PHP_INT_MAX, 'expected'];
        yield ['google', 0, 'expected'];
        yield ['google', PHP_INT_MAX, 'wrong'];
    }
}
