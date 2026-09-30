<?php

namespace Nurschool\Tests\Integration;

use Doctrine\ORM\{EntityManagerInterface, Tools\SchemaTool};
use Nurschool\Entity\User;
use Nurschool\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PasswordResetTokenTest extends KernelTestCase
{
    /** @dataProvider expiryBoundaries */
    public function testTokenExpiryBoundaryAndAtomicConsumption(string $offset, int $expected): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $issuedAt = new \DateTimeImmutable('2026-09-30 12:00:00 UTC');
        $token = str_repeat('a', 64);
        $user = (new User())->setEmail('boundary@example.com')->setPassword('old-hash');
        $user->requirePasswordReset(hash('sha256', $token), $issuedAt->modify('+3600 seconds'));
        $em->persist($user);
        $em->flush();
        $users = self::getContainer()->get(UserRepository::class);
        $now = $issuedAt->modify($offset);
        self::assertSame($expected, $users->consumePasswordResetToken($token, 'new-hash', $now));
        $em->clear();
        $stored = $users->findOneBy(['email' => 'boundary@example.com']);
        self::assertSame($expected === 1 ? 'new-hash' : 'old-hash', $stored->getPassword());
        self::assertFalse($stored->isVerified());
        if ($expected === 1) {
            self::assertSame(0, $users->consumePasswordResetToken($token, 'second-hash', $now));
            self::assertNull($em->getConnection()->fetchOne('SELECT password_reset_hash FROM user'));
            self::assertNull($em->getConnection()->fetchOne('SELECT password_reset_expires_at FROM user'));
        }
    }

    public function testConcurrentWorkersCanOnlyChangePasswordOnce(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        self::assertSame('pdo_sqlite', $connection->getParams()['driver']);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $user = (new User())->setEmail('race@example.com')->setPassword('old-hash');
        $user->requirePasswordReset(hash('sha256', str_repeat('a', 64)), new \DateTimeImmutable('2026-09-30 13:00:00 UTC'));
        $em->persist($user);
        $em->flush();
        $prefix = sys_get_temp_dir().'/nurschool-reset-race-'.bin2hex(random_bytes(8));
        $workers = [];
        try {
            foreach ([0, 1] as $index) {
                $process = proc_open([
                    PHP_BINARY, '-d', 'xdebug.mode=off', dirname(__DIR__).'/Fixtures/consume-password-reset.php',
                    $connection->getParams()['path'], $prefix.'.'.$index, $prefix.'.go', 'worker-'.$index,
                ], [1 => ['file', $prefix.'.'.$index.'.log', 'w'], 2 => ['file', $prefix.'.'.$index.'.log', 'a']], $pipes);
                self::assertIsResource($process);
                $workers[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (!is_file($prefix.'.0.ready') || !is_file($prefix.'.1.ready')) {
                if (microtime(true) > $deadline) {
                    self::fail('Concurrent reset workers failed to reach the barrier.');
                }
                usleep(10000);
            }
            file_put_contents($prefix.'.go', 'go');
            foreach ($workers as $index => $worker) {
                self::assertSame(0, proc_close($worker), file_get_contents($prefix.'.'.$index.'.log'));
                unset($workers[$index]);
            }
            $results = [file_get_contents($prefix.'.0'), file_get_contents($prefix.'.1')];
            self::assertEqualsCanonicalizing(['0', '1'], $results);
            $winner = $results[0] === '1' ? 'worker-0' : 'worker-1';
            self::assertSame($winner, $connection->fetchOne('SELECT password FROM user'));
            self::assertNull($connection->fetchOne('SELECT password_reset_hash FROM user'));
        } finally {
            foreach ($workers as $worker) {
                proc_terminate($worker);
                proc_close($worker);
            }
            foreach (glob($prefix.'.*') as $file) {
                unlink($file);
            }
        }
    }

    public static function expiryBoundaries(): iterable
    {
        yield 'one second before expiry' => ['+3599 seconds', 1];
        yield 'exactly one hour' => ['+3600 seconds', 0];
        yield 'one second after expiry' => ['+3601 seconds', 0];
    }
}
