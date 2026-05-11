<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123225353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE support_ticket (id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)', attachment_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', subject VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, order_id VARCHAR(50) DEFAULT NULL, machine VARCHAR(255) DEFAULT NULL, urgency VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_1F5A4D53464E68B (attachment_id), INDEX IDX_1F5A4D53A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53464E68B FOREIGN KEY (attachment_id) REFERENCES media_item (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D53464E68B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D53A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE support_ticket
        SQL);
    }
}
