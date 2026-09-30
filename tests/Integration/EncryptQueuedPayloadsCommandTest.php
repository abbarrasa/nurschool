<?php

namespace Nurschool\Tests\Integration;

use Doctrine\DBAL\Connection;
use Nurschool\Command\EncryptQueuedPayloadsCommand;
use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use Nurschool\Messenger\PayloadCipher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

final class EncryptQueuedPayloadsCommandTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        self::assertSame('pdo_sqlite', $this->connection->getParams()['driver']);
        $this->connection->executeStatement('DROP TABLE IF EXISTS messenger_messages');
        self::getContainer()->get('messenger.transport.sendgrid')->setup();
    }

    private function insertLegacy(string $queue, string $headers = '[]'): void
    {
        $email = (new DynamicTemplateEmail('d-'.str_repeat('a', 32), ['url' => 'https://school.example/#legacy-token']))
            ->from('school@example.com')->to('ana@example.com');
        $body = (new PhpSerializer())->encode(new Envelope(new SendEmailMessage($email)))['body'];
        $this->connection->insert('messenger_messages', [
            'body' => $body, 'headers' => $headers, 'queue_name' => $queue,
            'created_at' => '2026-09-30 12:00:00', 'available_at' => '2026-09-30 12:01:00', 'delivered_at' => null,
        ]);
    }

    public function testEncryptsLegacyRowsPreservesMetadataAndCanBeRepeated(): void
    {
        $this->insertLegacy('sendgrid', '{"X-Secret":"header-secret"}');
        $this->insertLegacy('failed');
        $this->insertLegacy('unrelated');
        $before = $this->connection->fetchAllAssociative('SELECT * FROM messenger_messages ORDER BY id');
        $tester = new CommandTester(self::getContainer()->get(EncryptQueuedPayloadsCommand::class));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Encrypted 2 queued messages.', $tester->getDisplay());
        $after = $this->connection->fetchAllAssociative('SELECT * FROM messenger_messages ORDER BY id');
        foreach ([0, 1] as $index) {
            self::assertStringStartsWith('v1:', $after[$index]['body']);
            self::assertStringNotContainsString('legacy-token', $after[$index]['body'].$after[$index]['headers']);
            self::assertStringNotContainsString('header-secret', $after[$index]['body'].$after[$index]['headers']);
            foreach (['id', 'queue_name', 'created_at', 'available_at', 'delivered_at'] as $field) {
                self::assertSame($before[$index][$field], $after[$index][$field]);
            }
        }
        self::assertSame($before[2], $after[2]);
        $message = iterator_to_array(self::getContainer()->get('messenger.transport.sendgrid')->all(), false)[0]->getMessage();
        self::assertSame('https://school.example/#legacy-token', $message->getMessage()->getTemplateData()['url']);
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Encrypted 0 queued messages.', $tester->getDisplay());
        self::assertSame($after, $this->connection->fetchAllAssociative('SELECT * FROM messenger_messages ORDER BY id'));
    }

    public function testMalformedRowRollsBackPreviouslyEncryptedRows(): void
    {
        $this->insertLegacy('sendgrid');
        $this->insertLegacy('failed', 'invalid-json');
        $before = $this->connection->fetchAllAssociative('SELECT * FROM messenger_messages ORDER BY id');
        $tester = new CommandTester(self::getContainer()->get(EncryptQueuedPayloadsCommand::class));
        self::assertSame(1, $tester->execute([]));
        self::assertStringNotContainsString('legacy-token', $tester->getDisplay());
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM messenger_messages ORDER BY id'));
    }

    public function testWrongKeyCannotSkipAlreadyEncryptedRows(): void
    {
        $this->insertLegacy('sendgrid');
        $original = new CommandTester(self::getContainer()->get(EncryptQueuedPayloadsCommand::class));
        self::assertSame(0, $original->execute([]));
        $before = $this->connection->fetchAllAssociative('SELECT * FROM messenger_messages ORDER BY id');
        $wrongKey = new CommandTester(new EncryptQueuedPayloadsCommand($this->connection, new PayloadCipher(base64_encode(str_repeat('x', 32)))));
        self::assertSame(1, $wrongKey->execute([]));
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM messenger_messages ORDER BY id'));
    }
}
