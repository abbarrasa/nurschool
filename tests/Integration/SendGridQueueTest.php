<?php

namespace Nurschool\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Worker;

final class SendGridQueueTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        self::assertSame('pdo_sqlite', $this->connection->getParams()['driver']);
        $this->connection->executeStatement('DROP TABLE IF EXISTS messenger_messages');
        require_once dirname(__DIR__, 2).'/migrations/Version20260923123000.php';
        $schema = new Schema();
        (new \DoctrineMigrations\Version20260923123000($this->connection, new NullLogger()))->up($schema);
        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    private function queue(): void
    {
        self::getContainer()->get(MailerInterface::class)->send((new DynamicTemplateEmail(
            'd-'.str_repeat('a', 32), ['url' => 'https://school.example/#token'],
        ))->from('school@example.com')->to('ana@example.com'));
    }

    private function consume(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $listener = new StopWorkerOnMessageLimitListener(1);
        $dispatcher->addSubscriber($listener);
        try {
            (new Worker(['sendgrid' => self::getContainer()->get('messenger.transport.sendgrid')],
                self::getContainer()->get(MessageBusInterface::class), $dispatcher))->run(['sleep' => 1000, 'time_limit' => 2]);
        } finally {
            $dispatcher->removeSubscriber($listener);
        }
    }

    private function fakeHttp(int $status): MockHttpClient
    {
        $http = new MockHttpClient(static fn () => new MockResponse('', ['http_code' => $status]));
        self::getContainer()->set('test.sendgrid_http_client', $http);
        return $http;
    }

    public function testDispatchPersistsWithoutSendingAndWorkerAcknowledges(): void
    {
        $http = $this->fakeHttp(202);
        $events = [];
        $sent = [];
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(MessageEvent::class, static function (MessageEvent $event) use (&$events): void {
            $events[] = $event->isQueued();
        });
        $dispatcher->addListener(SentMessageEvent::class, static function (SentMessageEvent $event) use (&$sent): void {
            $sent[] = $event;
        });
        $this->queue();
        self::assertSame([true], $events);
        $queued = iterator_to_array(self::getContainer()->get('messenger.transport.sendgrid')->all(), false)[0]->getMessage();
        self::assertInstanceOf(SendEmailMessage::class, $queued);
        $email = $queued->getMessage();
        self::assertInstanceOf(DynamicTemplateEmail::class, $email);
        self::assertSame('d-'.str_repeat('a', 32), $email->getTemplateId());
        self::assertSame(['url' => 'https://school.example/#token'], $email->getTemplateData());
        self::assertNull($email->getTextBody());
        self::assertNull($email->getHtmlBody());
        self::assertNull($email->getSubject());
        self::assertSame(0, $http->getRequestsCount());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->consume();
        self::assertSame(1, $http->getRequestsCount());
        self::assertSame([true, false], $events);
        self::assertCount(1, $sent);
        self::assertStringContainsString('MIME-Version:', $sent[0]->getMessage()->toString());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testRollbackRemovesQueuedMessage(): void
    {
        $this->connection->beginTransaction();
        $this->queue();
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->connection->rollBack();
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testPermanentFailureMovesDirectlyToFailedQueue(): void
    {
        $http = $this->fakeHttp(401);
        $failures = [];
        self::getContainer()->get('event_dispatcher')->addListener(FailedMessageEvent::class, static function (FailedMessageEvent $event) use (&$failures): void {
            $failures[] = $event;
        });
        $this->queue();
        $this->consume();
        self::assertSame(1, $http->getRequestsCount());
        self::assertCount(1, $failures);
        self::assertInstanceOf(\Symfony\Component\Mailer\Exception\TransportExceptionInterface::class, $failures[0]->getError());
        self::assertSame('failed', $this->connection->fetchOne('SELECT queue_name FROM messenger_messages'));
        self::assertSame(1, self::getContainer()->get('messenger.transport.failed')->getMessageCount());
    }

    public function testTransientFailureRetriesThreeTimesThenMovesToFailedQueue(): void
    {
        $http = $this->fakeHttp(503);
        $this->queue();
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $this->connection->executeStatement("UPDATE messenger_messages SET available_at = '2000-01-01 00:00:00'");
            $this->consume();
            self::assertSame($attempt, $http->getRequestsCount());
            self::assertSame($attempt === 4 ? 'failed' : 'sendgrid', $this->connection->fetchOne('SELECT queue_name FROM messenger_messages'));
            if ($attempt < 4) {
                $this->connection->executeStatement("UPDATE messenger_messages SET available_at = '2000-01-01 00:00:00'");
                $envelope = iterator_to_array(self::getContainer()->get('messenger.transport.sendgrid')->all(), false)[0];
                self::assertSame($attempt, RedeliveryStamp::getRetryCountFromEnvelope($envelope));
            }
        }
    }

    public function testMigrationCanBeReversed(): void
    {
        $schema = $this->connection->createSchemaManager()->introspectSchema();
        $before = clone $schema;
        (new \DoctrineMigrations\Version20260923123000($this->connection, new NullLogger()))->down($schema);
        $diff = $this->connection->createSchemaManager()->createComparator((new \Doctrine\DBAL\Schema\ComparatorConfig())->withReportModifiedIndexes(false))->compareSchemas($before, $schema);
        foreach ($this->connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) {
            $this->connection->executeStatement($sql);
        }
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['messenger_messages']));
    }
}
