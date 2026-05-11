<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250422121124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE machine ADD ib_station_number INT DEFAULT NULL, ADD ib_serial_number INT DEFAULT NULL, ADD article_number VARCHAR(255) DEFAULT NULL, ADD article_description VARCHAR(255) DEFAULT NULL, ADD order_number VARCHAR(255) DEFAULT NULL, ADD delivery_date DATE DEFAULT NULL, ADD kms_identification_number VARCHAR(255) DEFAULT NULL, ADD kms_id_number VARCHAR(255) DEFAULT NULL, ADD mc_number VARCHAR(255) DEFAULT NULL, ADD main_warranty_end DATE DEFAULT NULL, ADD extended_warranty_end DATE DEFAULT NULL, ADD fi_station_number INT DEFAULT NULL, ADD fi_serial_number INT DEFAULT NULL, DROP name, DROP slug');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE machine ADD name VARCHAR(255) NOT NULL, ADD slug VARCHAR(255) NOT NULL, DROP ib_station_number, DROP ib_serial_number, DROP article_number, DROP article_description, DROP order_number, DROP delivery_date, DROP kms_identification_number, DROP kms_id_number, DROP mc_number, DROP main_warranty_end, DROP extended_warranty_end, DROP fi_station_number, DROP fi_serial_number');
    }
}
