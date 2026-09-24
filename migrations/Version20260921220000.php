<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add single-use email verification; preserve access for existing accounts.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD verified TINYINT(1) DEFAULT 0 NOT NULL, ADD verification_hash VARCHAR(64) DEFAULT NULL, ADD verification_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE user SET verified = 1');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_VERIFICATION ON user (verification_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_VERIFICATION ON user');
        $this->addSql('ALTER TABLE user DROP verified, DROP verification_hash, DROP verification_expires_at');
    }
}
