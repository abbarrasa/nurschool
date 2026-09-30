<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store single-use password reset tokens and their expiry timestamps.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD password_reset_hash VARCHAR(64) DEFAULT NULL, ADD password_reset_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PASSWORD_RESET_HASH ON user (password_reset_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_PASSWORD_RESET_HASH ON user');
        $this->addSql('ALTER TABLE user DROP password_reset_hash, DROP password_reset_expires_at');
    }
}
