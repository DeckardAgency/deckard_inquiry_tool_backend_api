<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251204112115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE documentation (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, category VARCHAR(100) DEFAULT NULL, sort_order INT DEFAULT 0 NOT NULL, is_published TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_73D5A93B989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE documentation_revision (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', documentation_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', edited_by_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', content LONGTEXT NOT NULL, title LONGTEXT NOT NULL, edited_at DATETIME NOT NULL, change_note VARCHAR(255) DEFAULT NULL, revision_number INT NOT NULL, INDEX IDX_2E88A189C703EEC9 (documentation_id), INDEX IDX_2E88A189DD7B2EBC (edited_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documentation_revision ADD CONSTRAINT FK_2E88A189C703EEC9 FOREIGN KEY (documentation_id) REFERENCES documentation (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documentation_revision ADD CONSTRAINT FK_2E88A189DD7B2EBC FOREIGN KEY (edited_by_id) REFERENCES `user` (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE documentation_revision DROP FOREIGN KEY FK_2E88A189C703EEC9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documentation_revision DROP FOREIGN KEY FK_2E88A189DD7B2EBC
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE documentation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE documentation_revision
        SQL);
    }
}
