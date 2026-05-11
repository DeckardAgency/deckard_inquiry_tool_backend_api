<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251127154839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes to Order, Inquiry, Product, Client, ClientMachineInstalledBase, and Machine entities';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_client_code ON client (code)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_client_name ON client (name)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_cmib_installed_date ON client_machine_installed_base (installed_date)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_cmib_status ON client_machine_installed_base (status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX unique_client_machine ON client_machine_installed_base (client_id, machine_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_machine_installed_base RENAME INDEX idx_59ccbaff19eb6921 TO idx_cmib_client
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_machine_installed_base RENAME INDEX idx_59ccbafff6b75b26 TO idx_cmib_machine
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_inquiry_status ON inquiry (status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_inquiry_created_at ON inquiry (created_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_inquiry_number ON inquiry (inquiry_number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_inquiry_is_draft ON inquiry (is_draft)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry RENAME INDEX idx_5a3903f0a76ed395 TO idx_inquiry_user_id
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_machine_ib_station ON machine (ib_station_number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_machine_ib_serial ON machine (ib_serial_number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_machine_article ON machine (article_number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_machine_delivery_date ON machine (delivery_date)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_machine_warranty_end ON machine (main_warranty_end)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_order_status ON `order` (status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_order_created_at ON `order` (created_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_order_number ON `order` (order_number)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` RENAME INDEX idx_f5299398a76ed395 TO idx_order_user_id
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_product_part_no ON product (part_no)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_product_name ON product (name)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_product_slug ON product (slug)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX idx_client_code ON client
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_client_name ON client
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_cmib_installed_date ON client_machine_installed_base
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_cmib_status ON client_machine_installed_base
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX unique_client_machine ON client_machine_installed_base
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_machine_installed_base RENAME INDEX idx_cmib_client TO IDX_59CCBAFF19EB6921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_machine_installed_base RENAME INDEX idx_cmib_machine TO IDX_59CCBAFFF6B75B26
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_inquiry_status ON inquiry
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_inquiry_created_at ON inquiry
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_inquiry_number ON inquiry
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_inquiry_is_draft ON inquiry
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry RENAME INDEX idx_inquiry_user_id TO IDX_5A3903F0A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_machine_ib_station ON machine
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_machine_ib_serial ON machine
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_machine_article ON machine
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_machine_delivery_date ON machine
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_machine_warranty_end ON machine
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_order_status ON `order`
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_order_created_at ON `order`
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_order_number ON `order`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` RENAME INDEX idx_order_user_id TO IDX_F5299398A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_product_part_no ON product
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_product_name ON product
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_product_slug ON product
        SQL);
    }
}
