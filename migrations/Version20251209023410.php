<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209023410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tracking and cancellation fields to Order entity';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD dispatched_by_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD cancelled_by_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD tracking_number VARCHAR(100) DEFAULT NULL, ADD tracking_carrier VARCHAR(50) DEFAULT NULL, ADD tracking_url VARCHAR(500) DEFAULT NULL, ADD dispatched_at DATETIME DEFAULT NULL, ADD cancellation_reason LONGTEXT DEFAULT NULL, ADD cancelled_at DATETIME DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD CONSTRAINT FK_F52993984FF50408 FOREIGN KEY (dispatched_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD CONSTRAINT FK_F5299398187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F52993984FF50408 ON `order` (dispatched_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F5299398187B2D12 ON `order` (cancelled_by_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP FOREIGN KEY FK_F52993984FF50408
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398187B2D12
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_F52993984FF50408 ON `order`
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_F5299398187B2D12 ON `order`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP dispatched_by_id, DROP cancelled_by_id, DROP tracking_number, DROP tracking_carrier, DROP tracking_url, DROP dispatched_at, DROP cancellation_reason, DROP cancelled_at
        SQL);
    }
}
