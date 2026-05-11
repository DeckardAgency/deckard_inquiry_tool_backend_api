<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250530003245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE order_log (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', order_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', changed_by_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', previous_status VARCHAR(50) NOT NULL, new_status VARCHAR(50) NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, metadata JSON DEFAULT NULL, INDEX IDX_CC6427A5828AD0A0 (changed_by_id), INDEX idx_order_log_order (order_id), INDEX idx_order_log_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_log ADD CONSTRAINT FK_CC6427A58D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_log ADD CONSTRAINT FK_CC6427A5828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES `user` (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE order_log DROP FOREIGN KEY FK_CC6427A58D9F6D38
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_log DROP FOREIGN KEY FK_CC6427A5828AD0A0
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE order_log
        SQL);
    }
}
