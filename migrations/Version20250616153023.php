<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616153023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE client_machine_installed_base (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', client_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', machine_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', installed_date DATE DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, status VARCHAR(50) DEFAULT 'active' NOT NULL, warranty_end_date DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, installed_by VARCHAR(255) DEFAULT NULL, installation_reference VARCHAR(100) DEFAULT NULL, monthly_rate NUMERIC(10, 2) DEFAULT NULL, INDEX IDX_59CCBAFF19EB6921 (client_id), INDEX IDX_59CCBAFFF6B75B26 (machine_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_machine_installed_base ADD CONSTRAINT FK_59CCBAFF19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_machine_installed_base ADD CONSTRAINT FK_59CCBAFFF6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client ADD machines_count INT DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE machine ADD clients_count INT DEFAULT 0 NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client_machine_installed_base DROP FOREIGN KEY FK_59CCBAFF19EB6921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_machine_installed_base DROP FOREIGN KEY FK_59CCBAFFF6B75B26
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE client_machine_installed_base
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client DROP machines_count
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE machine DROP clients_count
        SQL);
    }
}
