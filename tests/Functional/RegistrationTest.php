<?php

namespace Nurschool\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nurschool\Entity\{Role, User};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class RegistrationTest extends WebTestCase
{
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        foreach (['ROLE_USER', 'ROLE_NURSE', 'ROLE_ADMIN'] as $name) {
            $em->persist((new Role())->setName($name)->setTranslationId('app.roles.user'));
        }
        $em->flush();
        self::getContainer()->get('messenger.transport.sendgrid')->setup();
        $em->getConnection()->executeStatement('DELETE FROM messenger_messages');
    }

    public function testRegistrationVerificationAndLogin(): void
    {
        $this->browser->enableProfiler();
        $this->browser->jsonRequest('POST', '/api/registrations?_locale=es', [
            'email' => ' NEW@example.com ', 'password' => 'secure-password', 'roles' => ['ROLE_NURSE', 'ROLE_ADMIN'],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertQueuedEmailCount(1);
        $transport = self::getContainer()->get('messenger.transport.sendgrid');
        self::assertSame(1, $transport->getMessageCount());
        $queued = iterator_to_array($transport->all(), false)[0]->getMessage();
        self::assertInstanceOf(\Symfony\Component\Mailer\Messenger\SendEmailMessage::class, $queued);
        $email = $queued->getMessage();
        self::assertInstanceOf(\Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail::class, $email);
        self::assertSame('new@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('d-'.str_repeat('a', 32), $email->getTemplateId());
        self::assertSame(1, preg_match('/#([a-f0-9]{64})/', $email->getTemplateData()['url'], $matches));
        $token = $matches[1];
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'new@example.com']);
        self::assertFalse($user->isVerified());
        self::assertTrue(password_verify('secure-password', $user->getPassword()));
        self::assertEqualsCanonicalizing(['ROLE_USER', 'ROLE_NURSE', 'ROLE_ADMIN'], $user->getRoles());
        self::assertStringNotContainsString($token, $this->browser->getResponse()->getContent());
        $this->browser->jsonRequest('POST', '/api/login', ['email' => 'new@example.com', 'password' => 'secure-password']);
        self::assertResponseStatusCodeSame(401);
        $this->browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        $this->browser->jsonRequest('POST', '/api/account-verifications', ['token' => $token]);
        self::assertResponseStatusCodeSame(200);
        $this->browser->jsonRequest('POST', '/api/account-verifications', ['token' => $token]);
        self::assertResponseStatusCodeSame(422);
        $this->browser->jsonRequest('POST', '/api/login', ['email' => 'new@example.com', 'password' => 'secure-password']);
        self::assertResponseStatusCodeSame(200);
        $this->browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(200);
    }

    public function testValidationAndDuplicateEmail(): void
    {
        foreach ([[], ['email' => 'bad', 'password' => 'secure-password'],
            ['email' => 'test@example.com', 'password' => 'short'],
            ['email' => 'test@example.com', 'password' => str_repeat('x', 73)],
            ['email' => 'test@example.com', 'password' => 'secure-password', 'roles' => ['ROLE_SUPER_ADMIN']],
            ['email' => 'test@example.com', 'password' => 'secure-password', 'roles' => 'ROLE_ADMIN'],
            ['email' => [], 'password' => []]] as $payload) {
            $this->browser->jsonRequest('POST', '/api/registrations', $payload);
            self::assertResponseStatusCodeSame(422);
        }
        $payload = ['email' => 'test@example.com', 'password' => 'secure-password'];
        $this->browser->jsonRequest('POST', '/api/registrations', $payload);
        self::assertResponseStatusCodeSame(201);
        $this->browser->jsonRequest('POST', '/api/registrations', $payload);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(1, self::getContainer()->get('messenger.transport.sendgrid')->getMessageCount());
        $this->browser->request('POST', '/api/registrations', $payload);
        self::assertResponseStatusCodeSame(415);
        $this->browser->request('POST', '/api/registrations', server: ['CONTENT_TYPE' => 'application/json'], content: '{');
        self::assertResponseStatusCodeSame(400);
    }

    public function testExpiredAndInvalidTokens(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $token = str_repeat('a', 64);
        $user = (new User())->setEmail('expired@example.com')->setPassword('unused');
        $user->requireVerification(hash('sha256', $token), new \DateTimeImmutable('-1 second'));
        $em->persist($user);
        $em->flush();
        foreach ([$token, str_repeat('b', 64), '', []] as $invalid) {
            $this->browser->jsonRequest('POST', '/api/account-verifications', ['token' => $invalid]);
            self::assertResponseStatusCodeSame(422);
        }
    }

    public function testEnqueueFailureRollsBackAccount(): void
    {
        self::getContainer()->get(\Doctrine\DBAL\Connection::class)->executeStatement('DROP TABLE messenger_messages');
        $this->browser->jsonRequest('POST', '/api/registrations', ['email' => 'retry@example.com', 'password' => 'secure-password']);
        self::assertResponseStatusCodeSame(500);
        $connection = self::getContainer()->get(\Doctrine\DBAL\Connection::class);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM user WHERE email = ?', ['retry@example.com']));
    }

    public function testEnglishRegistrationQueuesEnglishTemplate(): void
    {
        $this->browser->jsonRequest('POST', '/api/registrations?_locale=en', [
            'email' => 'english@example.com', 'password' => 'secure-password',
        ]);
        self::assertResponseStatusCodeSame(201);
        $message = iterator_to_array(self::getContainer()->get('messenger.transport.sendgrid')->all(), false)[0]->getMessage()->getMessage();
        self::assertSame('d-'.str_repeat('b', 32), $message->getTemplateId());
        self::assertStringContainsString('?_locale=en#', $message->getTemplateData()['url']);
    }

    public function testFormAndVerificationPage(): void
    {
        foreach (['es', 'en'] as $locale) {
            $this->browser->request('GET', '/register?_locale='.$locale);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('select[multiple]');
            self::assertSelectorExists('input[autocomplete="new-password"][required]');
            self::assertSelectorExists('script[src="/assets/registration.js"]');
        }
        $this->browser->request('GET', '/verify-account');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
    }
}
