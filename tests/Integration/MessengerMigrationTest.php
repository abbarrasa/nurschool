<?php

namespace Nurschool\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\DBALSchemaDiffProvider;
use Doctrine\Migrations\Provider\SchemaDiffProvider;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use DoctrineMigrations\Version20260923123000;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as QueueConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

final class MessengerMigrationTest extends TestCase
{
    private ?Connection $connection = null;

    protected function setUp(): void
    {
        $host = getenv('MESSENGER_MIGRATION_TEST_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('Set MESSENGER_MIGRATION_TEST_HOST to a disposable MariaDB container.');
        }
        // These fixed credentials target only the disposable test database, never DATABASE_URL.
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql', 'host' => $host, 'user' => 'migration_test',
            'password' => 'migration_test', 'dbname' => 'nurschool_migration_test', 'charset' => 'utf8mb4',
        ]);
        self::assertSame('nurschool_migration_test', $this->connection->fetchOne('SELECT DATABASE()'));
        require_once dirname(__DIR__, 2).'/migrations/Version20260923123000.php';
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS messenger_messages');
            $this->connection->executeStatement('DROP TABLE IF EXISTS migration_versions');
            $this->connection->close();
        }
    }

    private function migrate(string $version, bool $dryRun = false): void
    {
        // Each CLI invocation builds fresh migration instances; executed migrations are frozen.
        $migrations = DependencyFactory::fromConnection(new ConfigurationArray([
            'migrations' => [Version20260923123000::class],
            'table_storage' => ['table_name' => 'migration_versions'],
        ]), new ExistingConnection($this->connection));
        // Use Doctrine's eager provider: its optional lazy proxy uses deprecated vendor APIs.
        $migrations->setService(SchemaDiffProvider::class, new DBALSchemaDiffProvider(
            $this->connection->createSchemaManager(), $this->connection->getDatabasePlatform(),
        ));
        $tester = new CommandTester(new MigrateCommand($migrations));
        $tester->execute(['version' => $version, '--dry-run' => $dryRun], ['interactive' => false]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    public function testMigrateCreatesUsableQueueAndDownDropsIt(): void
    {
        $this->migrate('latest', true);
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['messenger_messages']));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_versions'));

        // Exercise the real command, including statement execution and migration version tracking.
        $this->migrate('latest');
        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['messenger_messages']));
        self::assertSame(Version20260923123000::class, $this->connection->fetchOne('SELECT version FROM migration_versions'));
        self::assertSame('InnoDB', $this->connection->fetchOne("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messenger_messages'"));
        $table = $this->connection->createSchemaManager()->introspectTable('messenger_messages');
        self::assertCount(7, $table->getColumns());
        foreach (['id', 'body', 'headers', 'queue_name', 'created_at', 'available_at', 'delivered_at'] as $column) {
            self::assertTrue($table->hasColumn($column));
        }
        self::assertTrue($table->getColumn('id')->getAutoincrement());
        self::assertFalse($table->getColumn('delivered_at')->getNotnull());
        self::assertSame(['queue_name', 'available_at', 'delivered_at', 'id'], $this->connection->fetchFirstColumn("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messenger_messages' AND INDEX_NAME = 'IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750' ORDER BY SEQ_IN_INDEX"));

        $transport = new DoctrineTransport(new QueueConnection([
            'queue_name' => 'sendgrid', 'auto_setup' => false,
        ], $this->connection), new PhpSerializer());
        $this->connection->beginTransaction();
        $transport->send(new Envelope(new \stdClass()));
        self::assertSame(1, $transport->getMessageCount());
        $this->connection->rollBack();
        self::assertSame(0, $transport->getMessageCount());
        $transport->send(new Envelope(new \stdClass()));
        $messages = iterator_to_array($transport->get(), false);
        self::assertCount(1, $messages);
        $transport->ack($messages[0]);
        self::assertSame(0, $transport->getMessageCount());

        $this->migrate('0');
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['messenger_messages']));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_versions'));
    }
}
