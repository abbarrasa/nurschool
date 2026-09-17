<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$_SERVER['KERNEL_CLASS'] = Nurschool\Kernel::class;
// Each PHPUnit process gets a disposable SQLite database, never the application database.
$databasePath = sys_get_temp_dir().'/nurschool-login-'.getmypid().'.sqlite';
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///'.$databasePath;
register_shutdown_function(static function () use ($databasePath): void {
    if (is_file($databasePath)) {
        unlink($databasePath);
    }
});
