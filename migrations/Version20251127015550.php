<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251127015550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_invitation (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', client_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', created_by_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, token VARCHAR(128) NOT NULL, status VARCHAR(20) NOT NULL, expires_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, roles JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_567AA74E5F37A13B (token), INDEX IDX_567AA74E19EB6921 (client_id), INDEX IDX_567AA74EB03A8386 (created_by_id), INDEX idx_user_invitation_token (token), INDEX idx_user_invitation_email (email), INDEX idx_user_invitation_status (status), INDEX idx_user_invitation_expires_at (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_invitation ADD CONSTRAINT FK_567AA74E19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_invitation ADD CONSTRAINT FK_567AA74EB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_invitation DROP FOREIGN KEY FK_567AA74E19EB6921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_invitation DROP FOREIGN KEY FK_567AA74EB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_invitation
        SQL);
    }
}
