<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250503152227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE inquiry_machine (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', inquiry_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', machine_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_FE18B121A7AD6D71 (inquiry_id), INDEX IDX_FE18B121F6B75B26 (machine_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inquiry_machine_part (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', inquiry_machine_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', part_name VARCHAR(255) NOT NULL, part_number VARCHAR(255) DEFAULT NULL, short_description LONGTEXT DEFAULT NULL, additional_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_D54F116DB17CEEB6 (inquiry_machine_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inquiry_machine ADD CONSTRAINT FK_FE18B121A7AD6D71 FOREIGN KEY (inquiry_id) REFERENCES inquiry (id)');
        $this->addSql('ALTER TABLE inquiry_machine ADD CONSTRAINT FK_FE18B121F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id)');
        $this->addSql('ALTER TABLE inquiry_machine_part ADD CONSTRAINT FK_D54F116DB17CEEB6 FOREIGN KEY (inquiry_machine_id) REFERENCES inquiry_machine (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inquiry_machine DROP FOREIGN KEY FK_FE18B121A7AD6D71');
        $this->addSql('ALTER TABLE inquiry_machine DROP FOREIGN KEY FK_FE18B121F6B75B26');
        $this->addSql('ALTER TABLE inquiry_machine_part DROP FOREIGN KEY FK_D54F116DB17CEEB6');
        $this->addSql('DROP TABLE inquiry_machine');
        $this->addSql('DROP TABLE inquiry_machine_part');
    }
}
