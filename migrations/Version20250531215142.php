<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250531215142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD product_document_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACD6F10E628 FOREIGN KEY (product_document_id) REFERENCES product (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC5CFACD6F10E628 ON media_item (product_document_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACD6F10E628
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_DC5CFACD6F10E628 ON media_item
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP product_document_id
        SQL);
    }
}
