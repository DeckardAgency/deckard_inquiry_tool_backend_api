<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250612163949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE machine_product (machine_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', product_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', INDEX IDX_D535941DF6B75B26 (machine_id), INDEX IDX_D535941D4584665A (product_id), PRIMARY KEY(machine_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE machine_product ADD CONSTRAINT FK_D535941DF6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE machine_product ADD CONSTRAINT FK_D535941D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE machine_product DROP FOREIGN KEY FK_D535941DF6B75B26
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE machine_product DROP FOREIGN KEY FK_D535941D4584665A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE machine_product
        SQL);
    }
}
