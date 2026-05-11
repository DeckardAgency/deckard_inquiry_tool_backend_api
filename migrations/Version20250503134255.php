<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250503134255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE inquiry (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', inquiry_number VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, contact_email VARCHAR(255) DEFAULT NULL, contact_phone VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_draft TINYINT(1) NOT NULL, last_saved_at DATETIME DEFAULT NULL, INDEX IDX_5A3903F0A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inquiry_item (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', inquiry_ref_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', quantity INT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, needs_quote TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_7351C17732EFDB6C (inquiry_ref_id), INDEX IDX_7351C1774584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inquiry ADD CONSTRAINT FK_5A3903F0A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE inquiry_item ADD CONSTRAINT FK_7351C17732EFDB6C FOREIGN KEY (inquiry_ref_id) REFERENCES inquiry (id)');
        $this->addSql('ALTER TABLE inquiry_item ADD CONSTRAINT FK_7351C1774584665A FOREIGN KEY (product_id) REFERENCES product (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inquiry DROP FOREIGN KEY FK_5A3903F0A76ED395');
        $this->addSql('ALTER TABLE inquiry_item DROP FOREIGN KEY FK_7351C17732EFDB6C');
        $this->addSql('ALTER TABLE inquiry_item DROP FOREIGN KEY FK_7351C1774584665A');
        $this->addSql('DROP TABLE inquiry');
        $this->addSql('DROP TABLE inquiry_item');
    }
}
