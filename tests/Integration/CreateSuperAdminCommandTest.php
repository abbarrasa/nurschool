<?php

namespace Nurschool\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nurschool\Command\CreateSuperAdminCommand;
use Nurschool\Entity\Role;
use Nurschool\Entity\User;
use Nurschool\Repository\RoleRepository;
use Nurschool\Repository\UserRepository;
use Nurschool\Validation\SuperAdminCredentialsValidator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateSuperAdminCommandTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    private function seedSuperAdminRole(): void
    {
        // Production roles are installed by the initial migration, not by the command.
        $role = (new Role())->setName('ROLE_SUPER_ADMIN')->setTranslationId('app.roles.super_admin');
        $this->em->persist($role);
        $this->em->flush();
    }

    public function testMissingSuperAdminRoleProducesEnglishErrorWithoutCreatingAccounts(): void
    {
        $tester = $this->runCommand(['admin@example.com', 'test-password-123', 'test-password-123']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('The super administrator role does not exist.', $tester->getDisplay());
        $this->assertNoAccountsCreated();
    }

    private function runCommand(array $inputs, bool $interactive = true): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:user:create-super-admin'));
        $tester->setInputs($inputs);
        $tester->execute([], ['interactive' => $interactive]);

        return $tester;
    }

    private function assertNoAccountsCreated(): void
    {
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM user'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM role'));
    }

    public function testCreatesHashedUserWithExactRoleAndCanAuthenticate(): void
    {
        $this->seedSuperAdminRole();
        $password = ' password with spaces ';
        $tester = $this->runCommand([' admin@example.com ', $password, $password]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString($password, $tester->getDisplay());
        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);
        self::assertSame(['ROLE_SUPER_ADMIN'], $user->getRoles());
        self::assertNotSame($password, $user->getPassword());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $password));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM user_role'));
        self::assertSame('app.roles.super_admin', $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN'])->getTranslationId());
        $this->browser->jsonRequest('POST', '/api/login', ['email' => 'admin@example.com', 'password' => $password]);
        self::assertResponseStatusCodeSame(200);
        $this->browser->request('GET', '/');
        self::assertResponseStatusCodeSame(200);
    }

    public function testReusesExistingRoleAcrossMultipleUsers(): void
    {
        $role = (new Role())->setName('ROLE_SUPER_ADMIN')->setTranslationId('existing.translation');
        $this->em->persist($role);
        $this->em->flush();
        foreach (['one@example.com', 'two@example.com'] as $email) {
            self::assertSame(Command::SUCCESS, $this->runCommand([$email, 'test-password-123', 'test-password-123'])->getStatusCode());
        }
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM role'));
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM user_role'));
        self::assertSame('existing.translation', $role->getTranslationId());
    }

    public function testLegacyRoleIsNotRenamedOrAssigned(): void
    {
        $this->seedSuperAdminRole();
        $legacy = (new Role())->setName('SUPER_ADMIN_ROLE')->setTranslationId('legacy.translation');
        $this->em->persist($legacy);
        $this->em->flush();
        self::assertSame(Command::SUCCESS, $this->runCommand(['admin@example.com', 'test-password-123', 'test-password-123'])->getStatusCode());
        $this->em->clear();
        self::assertSame(['ROLE_SUPER_ADMIN'], $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com'])->getRoles());
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM role'));
        self::assertNotNull($this->em->getRepository(Role::class)->findOneBy(['name' => 'SUPER_ADMIN_ROLE']));
    }

    public function testDuplicateEmailDoesNotPromoteOrChangeExistingAccount(): void
    {
        $user = (new User())->setEmail('existing@example.com')->setPassword('original-hash');
        $this->em->persist($user);
        $this->em->flush();
        $tester = $this->runCommand(['existing@example.com']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('A user with this email already exists', $tester->getDisplay());
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->find($user->getId());
        self::assertSame('original-hash', $stored->getPassword());
        self::assertSame([], $stored->getRoles());
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM role'));
    }

    public function testInvalidEmailCanBeCorrected(): void
    {
        $this->seedSuperAdminRole();
        $tester = $this->runCommand(['invalid', 'admin@example.com', 'test-password-123', 'test-password-123']);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Enter a valid email address', $tester->getDisplay());
    }

    public function testInvalidEmailExhaustsRetriesWithoutWrites(): void
    {
        $tester = $this->runCommand(['bad', 'bad', 'bad']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertNoAccountsCreated();
    }

    public function testInvalidPasswordsExhaustRetriesWithoutWritesOrDisclosure(): void
    {
        $tester = $this->runCommand(['admin@example.com', 'short-one', 'short-two', 'short-three']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        foreach (['short-one', 'short-two', 'short-three'] as $password) {
            self::assertStringNotContainsString($password, $tester->getDisplay());
        }
        $this->assertNoAccountsCreated();
    }

    public function testConfirmationCanBeCorrected(): void
    {
        $this->seedSuperAdminRole();
        $tester = $this->runCommand(['admin@example.com', 'test-password-123', 'different-secret', 'test-password-123']);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The passwords do not match', $tester->getDisplay());
        self::assertStringNotContainsString('different-secret', $tester->getDisplay());
    }

    public function testConfirmationFailureDoesNotCreateAccount(): void
    {
        $tester = $this->runCommand(['admin@example.com', 'test-password-123', 'wrong', 'wrong', 'wrong']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertNoAccountsCreated();
    }

    public function testEndOfInputDoesNotCreateAccount(): void
    {
        self::assertSame(Command::FAILURE, $this->runCommand(['admin@example.com'])->getStatusCode());
        $this->assertNoAccountsCreated();
    }

    public function testNonInteractiveExecutionIsRejected(): void
    {
        self::assertSame(Command::INVALID, $this->runCommand([], false)->getStatusCode());
        $this->assertNoAccountsCreated();
    }

    public function testDatabaseFailureProducesActionableError(): void
    {
        $this->em->getConnection()->executeStatement('DROP TABLE user');
        $tester = $this->runCommand(['admin@example.com']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Check the database connection', $tester->getDisplay());
    }

    public function testConcurrentDuplicatePreservesExistingRoleAndUser(): void
    {
        $this->seedSuperAdminRole();
        $user = (new User())->setEmail('admin@example.com')->setPassword('original-hash');
        $this->em->persist($user);
        $this->em->flush();
        // Simulate a stale duplicate check while exercising the real DB constraint and transaction.
        $staleRepository = $this->createMock(UserRepository::class);
        $staleRepository->method('findOneBy')->willReturn(null);
        $command = new CreateSuperAdminCommand($staleRepository,
            self::getContainer()->get(RoleRepository::class), $this->em,
            self::getContainer()->get(UserPasswordHasherInterface::class), new SuperAdminCredentialsValidator());
        $tester = new CommandTester($command);
        $tester->setInputs(['admin@example.com', 'test-password-123', 'test-password-123']);
        $tester->execute([]);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('created concurrently', $tester->getDisplay());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM user'));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM role'));
        self::assertSame('original-hash', $this->em->getConnection()->fetchOne('SELECT password FROM user'));
    }
}
