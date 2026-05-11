<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250422122116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE machine ADD featured_image_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF843569D950 FOREIGN KEY (featured_image_id) REFERENCES media_item (id)');
        $this->addSql('CREATE INDEX IDX_1505DF843569D950 ON machine (featured_image_id)');
        $this->addSql('ALTER TABLE media_item ADD machine_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACDF6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id)');
        $this->addSql('CREATE INDEX IDX_DC5CFACDF6B75B26 ON media_item (machine_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACDF6B75B26');
        $this->addSql('DROP INDEX IDX_DC5CFACDF6B75B26 ON media_item');
        $this->addSql('ALTER TABLE media_item DROP machine_id');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF843569D950');
        $this->addSql('DROP INDEX IDX_1505DF843569D950 ON machine');
        $this->addSql('ALTER TABLE machine DROP featured_image_id');
    }
}
