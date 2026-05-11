<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251119152627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE inquiry_machine_media_item (inquiry_machine_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', media_item_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', INDEX IDX_64C85CB4B17CEEB6 (inquiry_machine_id), INDEX IDX_64C85CB473B8D417 (media_item_id), PRIMARY KEY(inquiry_machine_id, media_item_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine_media_item ADD CONSTRAINT FK_64C85CB4B17CEEB6 FOREIGN KEY (inquiry_machine_id) REFERENCES inquiry_machine (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine_media_item ADD CONSTRAINT FK_64C85CB473B8D417 FOREIGN KEY (media_item_id) REFERENCES media_item (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine_media_item DROP FOREIGN KEY FK_64C85CB4B17CEEB6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine_media_item DROP FOREIGN KEY FK_64C85CB473B8D417
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE inquiry_machine_media_item
        SQL);
    }
}
