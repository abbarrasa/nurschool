<?php

namespace Nurschool\Command;

use Doctrine\DBAL\Connection;
use Nurschool\Messenger\PayloadCipher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:queue:encrypt-existing', description: 'Encrypt legacy SendGrid and failed queue payloads before starting the upgraded workers.')]
final class EncryptQueuedPayloadsCommand extends Command
{
    public function __construct(private readonly Connection $connection, private readonly PayloadCipher $cipher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $encrypted = 0;
        try {
            $this->connection->transactional(function () use (&$encrypted): void {
                $lastId = 0;
                do {
                    $rows = $this->connection->fetchAllAssociative("SELECT id, body, headers FROM messenger_messages WHERE queue_name IN ('sendgrid', 'failed') AND id > ? ORDER BY id LIMIT 100", [$lastId]);
                    foreach ($rows as $row) {
                        $lastId = (int) $row['id'];
                        if (str_starts_with($row['body'], 'v1:')) {
                            // Validate the key rather than silently skipping unreadable messages.
                            $this->cipher->decrypt($row['body']);
                            continue;
                        }
                        $headers = json_decode($row['headers'], true, flags: JSON_THROW_ON_ERROR);
                        if (!is_array($headers)) {
                            throw new \UnexpectedValueException('Invalid legacy queue headers.');
                        }
                        foreach ($headers as $name => $value) {
                            if (!is_string($name) || !is_string($value)) {
                                throw new \UnexpectedValueException('Invalid legacy queue headers.');
                            }
                        }
                        // Wrap serialized bytes without instantiating legacy PHP objects.
                        $body = $this->cipher->encrypt(json_encode(['body' => $row['body'], 'headers' => $headers], JSON_THROW_ON_ERROR));
                        $encrypted += $this->connection->executeStatement(
                            'UPDATE messenger_messages SET body = ?, headers = ? WHERE id = ? AND body = ?',
                            [$body, '[]', $lastId, $row['body']],
                        );
                    }
                } while (count($rows) === 100);
            });
        } catch (\JsonException | \UnexpectedValueException) {
            $output->writeln('<error>Queue encryption failed: check the encryption key and message format. No changes were committed.</error>');

            return Command::FAILURE;
        }
        $output->writeln(sprintf('Encrypted %d queued messages.', $encrypted));

        return Command::SUCCESS;
    }
}
