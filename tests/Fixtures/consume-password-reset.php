<?php

// Each worker uses the parent's disposable SQLite database, never DATABASE_URL from .env.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$root = dirname(__DIR__, 2);
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($root.'/.env');
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///'.$argv[1];
$kernel = new Nurschool\Kernel('test', true);
$kernel->boot();
$users = $kernel->getContainer()->get('test.service_container')->get(Nurschool\Repository\UserRepository::class);
file_put_contents($argv[2].'.ready', 'ready');
$deadline = microtime(true) + 10;
while (!is_file($argv[3])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent test barrier timed out.');
    }
    usleep(10000);
}
$count = $users->consumePasswordResetToken(str_repeat('a', 64), $argv[4], new DateTimeImmutable('2026-09-30 12:00:00 UTC'));
file_put_contents($argv[2], (string) $count);
$kernel->shutdown();
