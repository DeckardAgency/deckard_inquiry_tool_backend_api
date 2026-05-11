<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209033312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry ADD cancelled_by_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD cancellation_reason LONGTEXT DEFAULT NULL, ADD cancelled_at DATETIME DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry ADD CONSTRAINT FK_5A3903F0187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5A3903F0187B2D12 ON inquiry (cancelled_by_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry DROP FOREIGN KEY FK_5A3903F0187B2D12
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_5A3903F0187B2D12 ON inquiry
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry DROP cancelled_by_id, DROP cancellation_reason, DROP cancelled_at
        SQL);
    }
}
