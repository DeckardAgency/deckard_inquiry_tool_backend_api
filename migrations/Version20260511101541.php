<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511101541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              vehicle_make VARCHAR(64) DEFAULT NULL,
            ADD
              vehicle_model VARCHAR(64) DEFAULT NULL,
            ADD
              year_from VARCHAR(16) DEFAULT NULL,
            ADD
              year_to VARCHAR(16) DEFAULT NULL,
            ADD
              primary_category_code VARCHAR(64) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            DROP
              vehicle_make,
            DROP
              vehicle_model,
            DROP
              year_from,
            DROP
              year_to,
            DROP
              primary_category_code
        SQL);
    }
}
