<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126001447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE area_assignments (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', area_manager_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', inquiry_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', order_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', assigned_by_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', unassigned_by_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', assignment_type VARCHAR(50) NOT NULL, assignment_strategy VARCHAR(100) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, assigned_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', unassigned_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', assignment_reason LONGTEXT DEFAULT NULL, unassignment_reason LONGTEXT DEFAULT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_1BB1FAAD6E6F1246 (assigned_by_id), INDEX IDX_1BB1FAAD591D134D (unassigned_by_id), INDEX idx_area_assignment_manager (area_manager_id), INDEX idx_area_assignment_inquiry (inquiry_id), INDEX idx_area_assignment_order (order_id), INDEX idx_area_assignment_active (is_active), INDEX idx_area_assignment_assigned_at (assigned_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE area_criteria (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', area_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, field_type VARCHAR(50) NOT NULL, field_path VARCHAR(255) DEFAULT NULL, operator VARCHAR(50) NOT NULL, value JSON NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, priority INT DEFAULT 0 NOT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_area_criteria_area (area_id), INDEX idx_area_criteria_active (is_active), INDEX idx_area_criteria_priority (priority), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE area_manager_availabilities (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', area_manager_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', day_of_week SMALLINT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, timezone VARCHAR(50) NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, valid_from DATE DEFAULT NULL, valid_until DATE DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_area_manager_availability_manager (area_manager_id), INDEX idx_area_manager_availability_active (is_active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE area_managers (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', area_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', manager_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', is_primary TINYINT(1) DEFAULT 0 NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, max_capacity INT DEFAULT 0 NOT NULL, current_assignment_count INT DEFAULT 0 NOT NULL, specializations JSON DEFAULT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_area_manager_area (area_id), INDEX idx_area_manager_manager (manager_id), INDEX idx_area_manager_active (is_active), INDEX idx_area_manager_primary (is_primary), UNIQUE INDEX unique_area_manager (area_id, manager_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE areas (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', client_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', parent_area_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, code VARCHAR(50) NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, priority INT DEFAULT 0 NOT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_58B0B25C77153098 (code), INDEX idx_area_client (client_id), INDEX idx_area_parent (parent_area_id), INDEX idx_area_active (is_active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments ADD CONSTRAINT FK_1BB1FAADF7CED6D9 FOREIGN KEY (area_manager_id) REFERENCES area_managers (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments ADD CONSTRAINT FK_1BB1FAADA7AD6D71 FOREIGN KEY (inquiry_id) REFERENCES inquiry (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments ADD CONSTRAINT FK_1BB1FAAD8D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments ADD CONSTRAINT FK_1BB1FAAD6E6F1246 FOREIGN KEY (assigned_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments ADD CONSTRAINT FK_1BB1FAAD591D134D FOREIGN KEY (unassigned_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_criteria ADD CONSTRAINT FK_7510C549BD0F409C FOREIGN KEY (area_id) REFERENCES areas (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_manager_availabilities ADD CONSTRAINT FK_234D8322F7CED6D9 FOREIGN KEY (area_manager_id) REFERENCES area_managers (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_managers ADD CONSTRAINT FK_6A46BECEBD0F409C FOREIGN KEY (area_id) REFERENCES areas (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_managers ADD CONSTRAINT FK_6A46BECE783E3463 FOREIGN KEY (manager_id) REFERENCES `user` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE areas ADD CONSTRAINT FK_58B0B25C19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE areas ADD CONSTRAINT FK_58B0B25CCF4734DA FOREIGN KEY (parent_area_id) REFERENCES areas (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments DROP FOREIGN KEY FK_1BB1FAADF7CED6D9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments DROP FOREIGN KEY FK_1BB1FAADA7AD6D71
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments DROP FOREIGN KEY FK_1BB1FAAD8D9F6D38
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments DROP FOREIGN KEY FK_1BB1FAAD6E6F1246
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_assignments DROP FOREIGN KEY FK_1BB1FAAD591D134D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_criteria DROP FOREIGN KEY FK_7510C549BD0F409C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_manager_availabilities DROP FOREIGN KEY FK_234D8322F7CED6D9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_managers DROP FOREIGN KEY FK_6A46BECEBD0F409C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE area_managers DROP FOREIGN KEY FK_6A46BECE783E3463
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE areas DROP FOREIGN KEY FK_58B0B25C19EB6921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE areas DROP FOREIGN KEY FK_58B0B25CCF4734DA
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE area_assignments
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE area_criteria
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE area_manager_availabilities
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE area_managers
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE areas
        SQL);
    }
}
