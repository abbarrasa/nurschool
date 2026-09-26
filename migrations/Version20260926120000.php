<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store social identities independently of email addresses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE social_identity (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, provider VARCHAR(20) NOT NULL, subject VARCHAR(255) NOT NULL, INDEX IDX_SOCIAL_USER (user_id), UNIQUE INDEX UNIQ_SOCIAL_SUBJECT (provider, subject), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_bin` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE social_identity ADD CONSTRAINT FK_SOCIAL_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE social_identity');
    }
}
