<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250602171347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD machine_document_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACD629A4E51 FOREIGN KEY (machine_document_id) REFERENCES machine (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC5CFACD629A4E51 ON media_item (machine_document_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACD629A4E51
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_DC5CFACD629A4E51 ON media_item
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP machine_document_id
        SQL);
    }
}
