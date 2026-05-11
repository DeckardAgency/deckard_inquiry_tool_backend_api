<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251127000621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inquiry_log table for tracking inquiry status changes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE inquiry_log (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', inquiry_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', changed_by_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', previous_status VARCHAR(50) NOT NULL, new_status VARCHAR(50) NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, metadata JSON DEFAULT NULL, INDEX IDX_C34EEC91828AD0A0 (changed_by_id), INDEX idx_inquiry_log_inquiry (inquiry_id), INDEX idx_inquiry_log_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_log ADD CONSTRAINT FK_C34EEC91A7AD6D71 FOREIGN KEY (inquiry_id) REFERENCES inquiry (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_log ADD CONSTRAINT FK_C34EEC91828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES `user` (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_log DROP FOREIGN KEY FK_C34EEC91A7AD6D71
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_log DROP FOREIGN KEY FK_C34EEC91828AD0A0
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE inquiry_log
        SQL);
    }
}
