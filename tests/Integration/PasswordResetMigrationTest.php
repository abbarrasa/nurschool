<?php

namespace Nurschool\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\{UniqueConstraintViolationException};
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\{DBALSchemaDiffProvider, SchemaDiffProvider};
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use DoctrineMigrations\{Version20260814190123, Version20260930120000};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PasswordResetMigrationTest extends TestCase
{
    private ?Connection $connection = null;

    protected function setUp(): void
    {
        $host = getenv('PASSWORD_RESET_MIGRATION_TEST_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('Set PASSWORD_RESET_MIGRATION_TEST_HOST to a disposable MariaDB 11.4 container.');
        }
        // Fixed credentials isolate destructive migration tests from the application database.
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql', 'host' => $host, 'user' => 'migration_test',
            'password' => 'migration_test', 'dbname' => 'nurschool_migration_test', 'charset' => 'utf8mb4',
        ]);
        self::assertSame('nurschool_migration_test', $this->connection->fetchOne('SELECT DATABASE()'));
        self::assertMatchesRegularExpression('/^11\.4\..*MariaDB/', $this->connection->fetchOne('SELECT VERSION()'));
        require_once dirname(__DIR__, 2).'/migrations/Version20260814190123.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20260930120000.php';
        $this->migrate(Version20260814190123::class);
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            foreach (['user_role', 'user', 'role', 'password_reset_migration_versions'] as $table) {
                $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
            }
            $this->connection->close();
        }
    }

    private function migrate(string $version, bool $dryRun = false): void
    {
        $migrations = DependencyFactory::fromConnection(new ConfigurationArray([
            'migrations' => [Version20260814190123::class, Version20260930120000::class],
            'table_storage' => ['table_name' => 'password_reset_migration_versions'],
        ]), new ExistingConnection($this->connection));
        $migrations->setService(SchemaDiffProvider::class, new DBALSchemaDiffProvider(
            $this->connection->createSchemaManager(), $this->connection->getDatabasePlatform(),
        ));
        $tester = new CommandTester(new MigrateCommand($migrations));
        $tester->execute(['version' => $version, '--dry-run' => $dryRun], ['interactive' => false]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    public function testMigrationPreservesAccountsEnforcesUniqueHashesAndReverses(): void
    {
        $this->connection->insert('user', ['email' => 'migration@example.com', 'password' => 'existing-hash']);
        $this->migrate('latest', true);
        self::assertFalse($this->connection->createSchemaManager()->introspectTable('user')->hasColumn('password_reset_hash'));
        $this->migrate('latest');
        self::assertFalse($this->connection->isTransactionActive());
        $table = $this->connection->createSchemaManager()->introspectTable('user');
        self::assertTrue($table->hasColumn('password_reset_hash'));
        self::assertTrue($table->hasColumn('password_reset_expires_at'));
        self::assertFalse($table->getColumn('password_reset_hash')->getNotnull());
        self::assertSame('existing-hash', $this->connection->fetchOne('SELECT password FROM user'));
        self::assertNull($this->connection->fetchOne('SELECT password_reset_hash FROM user'));
        $hash = str_repeat('a', 64);
        $this->connection->executeStatement('UPDATE user SET password_reset_hash = ?', [$hash]);
        try {
            $this->connection->insert('user', ['email' => 'duplicate@example.com', 'password' => 'unused', 'password_reset_hash' => $hash]);
            self::fail('Duplicate token hashes must be rejected.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user'));
        }
        $this->migrate('latest');
        self::assertCount(2, $this->versions());
        $this->migrate(Version20260814190123::class);
        self::assertFalse($this->connection->createSchemaManager()->introspectTable('user')->hasColumn('password_reset_hash'));
        self::assertSame('existing-hash', $this->connection->fetchOne('SELECT password FROM user'));
        $this->migrate('latest');
        self::assertTrue($this->connection->createSchemaManager()->introspectTable('user')->hasColumn('password_reset_hash'));
    }

    /** @return list<string> */
    private function versions(): array
    {
        return $this->connection->fetchFirstColumn('SELECT version FROM password_reset_migration_versions ORDER BY version');
    }
}
