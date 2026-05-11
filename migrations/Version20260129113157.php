<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129113157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE dispatch_document (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', order_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_path VARCHAR(500) NOT NULL, document_type VARCHAR(50) NOT NULL, file_size INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_dispatch_document_order (order_id), INDEX idx_dispatch_document_type (document_type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dispatch_document ADD CONSTRAINT FK_3FF9D7E08D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE dispatch_document DROP FOREIGN KEY FK_3FF9D7E08D9F6D38
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE dispatch_document
        SQL);
    }
}
