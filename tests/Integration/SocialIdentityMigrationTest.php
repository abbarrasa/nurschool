<?php

namespace Nurschool\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\{ForeignKeyConstraintViolationException, UniqueConstraintViolationException};
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\{DBALSchemaDiffProvider, SchemaDiffProvider};
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use DoctrineMigrations\{Version20260814190123, Version20260926120000};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SocialIdentityMigrationTest extends TestCase
{
    private ?Connection $connection = null;

    protected function setUp(): void
    {
        $host = getenv('SOCIAL_MIGRATION_TEST_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('Set SOCIAL_MIGRATION_TEST_HOST to a disposable MariaDB 11.4 container.');
        }
        // Fixed credentials isolate destructive migration tests from the application database.
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql', 'host' => $host, 'user' => 'migration_test',
            'password' => 'migration_test', 'dbname' => 'nurschool_migration_test', 'charset' => 'utf8mb4',
        ]);
        self::assertSame('nurschool_migration_test', $this->connection->fetchOne('SELECT DATABASE()'));
        self::assertMatchesRegularExpression('/^11\.4\..*MariaDB/', $this->connection->fetchOne('SELECT VERSION()'));
        require_once dirname(__DIR__, 2).'/migrations/Version20260814190123.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20260926120000.php';
        $this->migrate(Version20260814190123::class);
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            foreach (['social_identity', 'user_role', 'user', 'role', 'social_migration_versions'] as $table) {
                $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
            }
            $this->connection->close();
        }
    }

    private function migrate(string $version, bool $dryRun = false): void
    {
        $migrations = DependencyFactory::fromConnection(new ConfigurationArray([
            'migrations' => [Version20260814190123::class, Version20260926120000::class],
            'table_storage' => ['table_name' => 'social_migration_versions'],
        ]), new ExistingConnection($this->connection));
        $migrations->setService(SchemaDiffProvider::class, new DBALSchemaDiffProvider(
            $this->connection->createSchemaManager(), $this->connection->getDatabasePlatform(),
        ));
        $tester = new CommandTester(new MigrateCommand($migrations));
        $tester->execute(['version' => $version, '--dry-run' => $dryRun], ['interactive' => false]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    public function testMariaDbMigrationTracksVersionEnforcesConstraintsAndCanBeReversed(): void
    {
        $this->migrate('latest', true);
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['social_identity']));
        self::assertSame([Version20260814190123::class], $this->versions());

        // Run the real command: SQLite SchemaTool cannot detect MariaDB's implicit DDL commits.
        $this->migrate('latest');
        self::assertSame([Version20260814190123::class, Version20260926120000::class], $this->versions());
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame('InnoDB', $this->connection->fetchOne("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'social_identity'"));
        $this->connection->insert('user', ['email' => 'migration@example.com', 'password' => 'unused']);
        $userId = (int) $this->connection->lastInsertId();
        $identity = ['user_id' => $userId, 'provider' => 'google', 'subject' => 'subject'];
        $this->connection->insert('social_identity', $identity);
        try {
            $this->connection->insert('social_identity', $identity);
            self::fail('Duplicate provider identities must be rejected.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM social_identity'));
        }
        // Provider namespaces and case-sensitive subjects must remain independent.
        $this->connection->insert('social_identity', [...$identity, 'provider' => 'facebook']);
        $this->connection->insert('social_identity', [...$identity, 'subject' => 'SUBJECT']);
        try {
            $this->connection->insert('social_identity', [...$identity, 'user_id' => $userId + 1, 'subject' => 'orphan']);
            self::fail('An identity must reference an existing user.');
        } catch (ForeignKeyConstraintViolationException) {
            self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM social_identity'));
        }

        // A second migration command must not try to recreate the existing table.
        $this->migrate('latest');
        self::assertCount(2, $this->versions());
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM social_identity'));
        $this->connection->delete('user', ['id' => $userId]);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM social_identity'));

        $this->migrate(Version20260814190123::class);
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['social_identity']));
        self::assertSame([Version20260814190123::class], $this->versions());
        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['user']));
        self::assertFalse($this->connection->isTransactionActive());
        $this->migrate('latest');
        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['social_identity']));
        self::assertCount(2, $this->versions());
    }

    /** @return list<string> */
    private function versions(): array
    {
        return $this->connection->fetchFirstColumn('SELECT version FROM social_migration_versions ORDER BY version');
    }
}
