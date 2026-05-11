<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251111194518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client_product_price DROP FOREIGN KEY FK_1C2263B14584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_product_price ADD CONSTRAINT FK_1C2263B14584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client_product_price DROP FOREIGN KEY FK_1C2263B14584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_product_price ADD CONSTRAINT FK_1C2263B14584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
    }
}
