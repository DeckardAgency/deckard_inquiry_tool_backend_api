<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227212305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE inquiry_offer (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', inquiry_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', pdf_document_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', created_by_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', offer_number VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, total_amount DOUBLE PRECISION NOT NULL, rejection_reason LONGTEXT DEFAULT NULL, responded_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_1DD41426CBFD05C (pdf_document_id), INDEX IDX_1DD41426B03A8386 (created_by_id), INDEX idx_inquiry_offer_inquiry (inquiry_id), INDEX idx_inquiry_offer_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inquiry_offer_item (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', inquiry_offer_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', inquiry_machine_part_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', quantity INT NOT NULL, unit_price DOUBLE PRECISION NOT NULL, subtotal DOUBLE PRECISION NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_A628BE9993F4E287 (inquiry_machine_part_id), INDEX idx_inquiry_offer_item_offer (inquiry_offer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer ADD CONSTRAINT FK_1DD41426A7AD6D71 FOREIGN KEY (inquiry_id) REFERENCES inquiry (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer ADD CONSTRAINT FK_1DD41426CBFD05C FOREIGN KEY (pdf_document_id) REFERENCES media_item (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer ADD CONSTRAINT FK_1DD41426B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer_item ADD CONSTRAINT FK_A628BE99F5913EE7 FOREIGN KEY (inquiry_offer_id) REFERENCES inquiry_offer (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer_item ADD CONSTRAINT FK_A628BE9993F4E287 FOREIGN KEY (inquiry_machine_part_id) REFERENCES inquiry_machine_part (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer DROP FOREIGN KEY FK_1DD41426A7AD6D71
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer DROP FOREIGN KEY FK_1DD41426CBFD05C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer DROP FOREIGN KEY FK_1DD41426B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer_item DROP FOREIGN KEY FK_A628BE99F5913EE7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_offer_item DROP FOREIGN KEY FK_A628BE9993F4E287
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE inquiry_offer
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE inquiry_offer_item
        SQL);
    }
}
