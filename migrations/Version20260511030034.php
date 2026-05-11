<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511030034 extends AbstractMigration
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
              pim_id VARCHAR(36) DEFAULT NULL,
            ADD
              pim_synced_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            ADD
              pim_data_hash VARCHAR(64) DEFAULT NULL,
            ADD
              is_from_pim TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_D34A04AD7E8E8AA2 ON product (pim_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_D34A04AD7E8E8AA2 ON product
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP pim_id, DROP pim_synced_at, DROP pim_data_hash, DROP is_from_pim
        SQL);
    }
}
