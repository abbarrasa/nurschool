<?php

namespace Nurschool\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nurschool\Entity\{Role, User};
use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasswordResetTest extends WebTestCase
{
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        self::getContainer()->get('cache.password_reset_requests')->clear();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $role = (new Role())->setName('ROLE_USER')->setTranslationId('app.roles.user');
        $user = (new User())->setEmail('ana@example.com')->setPassword(password_hash('original-password', PASSWORD_BCRYPT))->markVerified();
        $user->addRole($role);
        $em->persist($role);
        $em->persist($user);
        $em->flush();
        self::getContainer()->get('messenger.transport.sendgrid')->setup();
        $em->getConnection()->executeStatement('DELETE FROM messenger_messages');
    }

    private function requestToken(string $locale = 'es', int $ttl = 3600): string
    {
        $this->browser->jsonRequest('POST', '/api/password-reset-requests?_locale='.$locale, ['email' => ' ANA@example.com ']);
        self::assertResponseStatusCodeSame(202);
        $messages = iterator_to_array(self::getContainer()->get('messenger.transport.sendgrid')->all(), false);
        $email = end($messages)->getMessage()->getMessage();
        self::assertInstanceOf(DynamicTemplateEmail::class, $email);
        self::assertSame('d-'.str_repeat('c', 32), $email->getTemplateId());
        self::assertSame($ttl, $email->getTemplateData()['ttl']);
        self::assertSame($locale, $email->getTemplateData()['locale']);
        self::assertSame('ana@example.com', $email->getTo()[0]->getAddress());
        self::assertStringContainsString('/reset-password?_locale='.$locale.'#', $email->getTemplateData()['url']);
        self::assertSame(1, preg_match('/#([a-f0-9]{64})$/', $email->getTemplateData()['url'], $matches));
        return $matches[1];
    }

    public function testResetChangesLoginPasswordAndCannotBeReused(): void
    {
        $before = new \DateTimeImmutable();
        $token = $this->requestToken('en');
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $row = $connection->fetchAssociative('SELECT password_reset_hash, password_reset_expires_at FROM user WHERE email = ?', ['ana@example.com']);
        self::assertSame(hash('sha256', $token), $row['password_reset_hash']);
        self::assertEqualsWithDelta($before->getTimestamp() + 3600, (new \DateTimeImmutable($row['password_reset_expires_at']))->getTimestamp(), 3);
        $this->browser->jsonRequest('POST', '/api/password-resets', ['token' => $token, 'password' => 'replacement-password']);
        self::assertResponseStatusCodeSame(200);
        $this->browser->jsonRequest('POST', '/api/password-resets', ['token' => $token, 'password' => 'another-password']);
        self::assertResponseStatusCodeSame(422);
        $this->browser->jsonRequest('POST', '/api/login', ['email' => 'ana@example.com', 'password' => 'original-password']);
        self::assertResponseStatusCodeSame(401);
        $this->browser->jsonRequest('POST', '/api/login', ['email' => 'ana@example.com', 'password' => 'replacement-password']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testUnknownEmailHasIdenticalResponseWithoutEmail(): void
    {
        $this->browser->jsonRequest('POST', '/api/password-reset-requests?_locale=es', ['email' => 'unknown@example.com']);
        self::assertResponseStatusCodeSame(202);
        $unknown = $this->browser->getResponse()->getContent();
        self::assertSame(0, self::getContainer()->get('messenger.transport.sendgrid')->getMessageCount());
        $this->requestToken();
        self::assertSame($unknown, $this->browser->getResponse()->getContent());
    }

    public function testNewRequestInvalidatesPreviousLink(): void
    {
        $old = $this->requestToken();
        $new = $this->requestToken();
        self::assertNotSame($old, $new);
        $this->browser->jsonRequest('POST', '/api/password-resets', ['token' => $old, 'password' => 'replacement-password']);
        self::assertResponseStatusCodeSame(422);
        $this->browser->jsonRequest('POST', '/api/password-resets', ['token' => $new, 'password' => 'replacement-password']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testInvalidPasswordDoesNotConsumeToken(): void
    {
        $token = $this->requestToken();
        foreach (['short', str_repeat('x', 73), [], str_repeat('é', 37)] as $password) {
            $this->browser->jsonRequest('POST', '/api/password-resets', ['token' => $token, 'password' => $password]);
            self::assertResponseStatusCodeSame(422);
        }
        $this->browser->jsonRequest('POST', '/api/password-resets', ['token' => $token, 'password' => 'replacement-password']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testExpiredAndMalformedTokensAreRejected(): void
    {
        $token = $this->requestToken();
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE user SET password_reset_expires_at = ?', [(new \DateTimeImmutable('-1 second'))->format('Y-m-d H:i:s')]);
        foreach ([$token, str_repeat('a', 64), '', []] as $invalid) {
            $this->browser->jsonRequest('POST', '/api/password-resets?_locale=en', ['token' => $invalid, 'password' => 'replacement-password']);
            self::assertResponseStatusCodeSame(422);
            self::assertStringContainsString('expired', $this->browser->getResponse()->getContent());
        }
    }

    public function testResetDoesNotVerifyAnAccount(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE user SET verified = 0');
        $token = $this->requestToken();
        $this->browser->jsonRequest('POST', '/api/password-resets', ['token' => $token, 'password' => 'replacement-password']);
        self::assertResponseStatusCodeSame(200);
        $this->browser->jsonRequest('POST', '/api/login', ['email' => 'ana@example.com', 'password' => 'replacement-password']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testEnqueueFailureRollsBackToken(): void
    {
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement('DROP TABLE messenger_messages');
        $this->browser->jsonRequest('POST', '/api/password-reset-requests', ['email' => 'ana@example.com']);
        self::assertResponseStatusCodeSame(500);
        self::assertNull(self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT password_reset_hash FROM user'));
    }

    public function testEnvironmentConfiguresLimitAndLifetime(): void
    {
        $previousTtl = $_SERVER['PASSWORD_RESET_TTL'];
        $previousLimit = $_SERVER['PASSWORD_RESET_REQUEST_LIMIT'];
        try {
            $_SERVER['PASSWORD_RESET_TTL'] = $_ENV['PASSWORD_RESET_TTL'] = '600';
            $_SERVER['PASSWORD_RESET_REQUEST_LIMIT'] = $_ENV['PASSWORD_RESET_REQUEST_LIMIT'] = '2';
            self::ensureKernelShutdown();
            $this->browser = self::createClient();
            $before = new \DateTimeImmutable();
            $this->requestToken('es', 600);
            $expiry = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT password_reset_expires_at FROM user');
            self::assertEqualsWithDelta($before->getTimestamp() + 600, (new \DateTimeImmutable($expiry))->getTimestamp(), 3);
            $this->requestToken('es', 600);
            $this->browser->jsonRequest('POST', '/api/password-reset-requests', ['email' => 'ana@example.com']);
            self::assertResponseStatusCodeSame(429);
        } finally {
            $_SERVER['PASSWORD_RESET_TTL'] = $_ENV['PASSWORD_RESET_TTL'] = $previousTtl;
            $_SERVER['PASSWORD_RESET_REQUEST_LIMIT'] = $_ENV['PASSWORD_RESET_REQUEST_LIMIT'] = $previousLimit;
            self::ensureKernelShutdown();
        }
    }

    public function testFourthRequestIsThrottledForKnownAndUnknownAccounts(): void
    {
        foreach (['ana@example.com', 'unknown@example.com'] as $email) {
            for ($attempt = 0; $attempt < 3; ++$attempt) {
                $this->browser->jsonRequest('POST', '/api/password-reset-requests?_locale=en', ['email' => $email]);
                self::assertResponseStatusCodeSame(202);
            }
            $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
            $hash = $connection->fetchOne('SELECT password_reset_hash FROM user');
            $this->browser->jsonRequest('POST', '/api/password-reset-requests?_locale=en', ['email' => ' '.strtoupper($email).' ']);
            self::assertResponseStatusCodeSame(429);
            self::assertStringContainsString('request limit', $this->browser->getResponse()->getContent());
            self::assertGreaterThan(0, (int) $this->browser->getResponse()->headers->get('Retry-After'));
            self::assertSame(3, self::getContainer()->get('messenger.transport.sendgrid')->getMessageCount());
            self::assertSame($hash, self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT password_reset_hash FROM user'));
        }
    }

    public function testPagesAndRequestValidation(): void
    {
        foreach (['es', 'en'] as $locale) {
            foreach (['/forgot-password', '/reset-password'] as $path) {
                $this->browser->request('GET', $path.'?_locale='.$locale);
                self::assertResponseIsSuccessful();
                self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
                self::assertSelectorExists('#password-reset-app');
                self::assertSelectorExists('script[src="/assets/password-reset.js"]');
            }
        }
        $this->browser->request('GET', '/login');
        self::assertSelectorExists('a[href="/forgot-password"]');
        foreach ([[], ['email' => []], ['email' => 'invalid']] as $payload) {
            $this->browser->jsonRequest('POST', '/api/password-reset-requests', $payload);
            self::assertResponseStatusCodeSame(422);
        }
        $this->browser->request('POST', '/api/password-reset-requests');
        self::assertResponseStatusCodeSame(415);
        $this->browser->request('POST', '/api/password-resets', server: ['CONTENT_TYPE' => 'application/json'], content: '{');
        self::assertResponseStatusCodeSame(400);
    }
}
