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

// Never use live SendGrid credentials or templates in automated tests.
$_SERVER['SENDGRID_VERIFICATION_TEMPLATE_ES'] = $_ENV['SENDGRID_VERIFICATION_TEMPLATE_ES'] = 'd-'.str_repeat('a', 32);
$_SERVER['SENDGRID_VERIFICATION_TEMPLATE_EN'] = $_ENV['SENDGRID_VERIFICATION_TEMPLATE_EN'] = 'd-'.str_repeat('b', 32);
$_SERVER['SENDGRID_PASSWORD_RESET_TEMPLATE_ES'] = $_ENV['SENDGRID_PASSWORD_RESET_TEMPLATE_ES'] = 'd-'.str_repeat('c', 32);
$_SERVER['SENDGRID_PASSWORD_RESET_TEMPLATE_EN'] = $_ENV['SENDGRID_PASSWORD_RESET_TEMPLATE_EN'] = 'd-'.str_repeat('c', 32);

// Keep recovery configuration deterministic regardless of local environment overrides.
$_SERVER['PASSWORD_RESET_TTL'] = $_ENV['PASSWORD_RESET_TTL'] = '3600';
$_SERVER['PASSWORD_RESET_REQUEST_LIMIT'] = $_ENV['PASSWORD_RESET_REQUEST_LIMIT'] = '3';
$_SERVER['PASSWORD_RESET_REQUEST_INTERVAL'] = $_ENV['PASSWORD_RESET_REQUEST_INTERVAL'] = '1 hour';
