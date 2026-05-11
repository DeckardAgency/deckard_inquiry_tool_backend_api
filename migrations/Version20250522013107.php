<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250522013107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE inquiry_machine_part_media_item (inquiry_machine_part_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', media_item_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_A3ED8FF293F4E287 (inquiry_machine_part_id), INDEX IDX_A3ED8FF273B8D417 (media_item_id), PRIMARY KEY(inquiry_machine_part_id, media_item_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inquiry_machine_part_media_item ADD CONSTRAINT FK_A3ED8FF293F4E287 FOREIGN KEY (inquiry_machine_part_id) REFERENCES inquiry_machine_part (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inquiry_machine_part_media_item ADD CONSTRAINT FK_A3ED8FF273B8D417 FOREIGN KEY (media_item_id) REFERENCES media_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inquiry_item DROP FOREIGN KEY FK_7351C17732EFDB6C');
        $this->addSql('ALTER TABLE inquiry_item DROP FOREIGN KEY FK_7351C1774584665A');
        $this->addSql('DROP TABLE inquiry_item');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE inquiry_item (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', inquiry_ref_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', quantity INT NOT NULL, comment LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, needs_quote TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_7351C17732EFDB6C (inquiry_ref_id), INDEX IDX_7351C1774584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE inquiry_item ADD CONSTRAINT FK_7351C17732EFDB6C FOREIGN KEY (inquiry_ref_id) REFERENCES inquiry (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE inquiry_item ADD CONSTRAINT FK_7351C1774584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE inquiry_machine_part_media_item DROP FOREIGN KEY FK_A3ED8FF293F4E287');
        $this->addSql('ALTER TABLE inquiry_machine_part_media_item DROP FOREIGN KEY FK_A3ED8FF273B8D417');
        $this->addSql('DROP TABLE inquiry_machine_part_media_item');
    }
}
