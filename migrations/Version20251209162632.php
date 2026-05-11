<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209162632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add brute force protection fields to user table (failed_login_attempts, locked_until, last_failed_login_at)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD failed_login_attempts INT DEFAULT 0 NOT NULL, ADD locked_until DATETIME DEFAULT NULL, ADD last_failed_login_at DATETIME DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP failed_login_attempts, DROP locked_until, DROP last_failed_login_at
        SQL);
    }
}
