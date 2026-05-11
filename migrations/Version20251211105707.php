<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211105707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Part Information Request system - creates tables for inquiry_part_info_request, inquiry_part_info_message, and adds infoStatus field to inquiry_machine_part';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE inquiry_part_info_message (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', info_request_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', sender_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', sender_type VARCHAR(20) NOT NULL, message_text LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_3BF504C5F624B39D (sender_id), INDEX idx_info_message_request (info_request_id), INDEX idx_info_message_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inquiry_part_info_message_media_item (inquiry_part_info_message_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', media_item_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', INDEX IDX_62C4E303589E5237 (inquiry_part_info_message_id), INDEX IDX_62C4E30373B8D417 (media_item_id), PRIMARY KEY(inquiry_part_info_message_id, media_item_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inquiry_part_info_request (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', inquiry_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', inquiry_machine_part_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', created_by_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_B6DFBB25B03A8386 (created_by_id), INDEX idx_info_request_inquiry (inquiry_id), INDEX idx_info_request_part (inquiry_machine_part_id), INDEX idx_info_request_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_message ADD CONSTRAINT FK_3BF504C51ABB8409 FOREIGN KEY (info_request_id) REFERENCES inquiry_part_info_request (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_message ADD CONSTRAINT FK_3BF504C5F624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_message_media_item ADD CONSTRAINT FK_62C4E303589E5237 FOREIGN KEY (inquiry_part_info_message_id) REFERENCES inquiry_part_info_message (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_message_media_item ADD CONSTRAINT FK_62C4E30373B8D417 FOREIGN KEY (media_item_id) REFERENCES media_item (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_request ADD CONSTRAINT FK_B6DFBB25A7AD6D71 FOREIGN KEY (inquiry_id) REFERENCES inquiry (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_request ADD CONSTRAINT FK_B6DFBB2593F4E287 FOREIGN KEY (inquiry_machine_part_id) REFERENCES inquiry_machine_part (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_request ADD CONSTRAINT FK_B6DFBB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine_part ADD info_status VARCHAR(50) DEFAULT 'none' NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_message DROP FOREIGN KEY FK_3BF504C51ABB8409
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_message DROP FOREIGN KEY FK_3BF504C5F624B39D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_message_media_item DROP FOREIGN KEY FK_62C4E303589E5237
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_message_media_item DROP FOREIGN KEY FK_62C4E30373B8D417
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_request DROP FOREIGN KEY FK_B6DFBB25A7AD6D71
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_request DROP FOREIGN KEY FK_B6DFBB2593F4E287
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_part_info_request DROP FOREIGN KEY FK_B6DFBB25B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE inquiry_part_info_message
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE inquiry_part_info_message_media_item
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE inquiry_part_info_request
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine_part DROP info_status
        SQL);
    }
}
