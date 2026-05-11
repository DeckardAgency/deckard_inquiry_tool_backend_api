<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251128075047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CASCADE delete to MediaItem foreign keys for Product and Machine relationships';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACD4584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACD629A4E51
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACD6F10E628
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACDF6B75B26
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACD4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACD629A4E51 FOREIGN KEY (machine_document_id) REFERENCES machine (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACD6F10E628 FOREIGN KEY (product_document_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACDF6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACD4584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACDF6B75B26
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACD6F10E628
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item DROP FOREIGN KEY FK_DC5CFACD629A4E51
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACD4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACDF6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACD6F10E628 FOREIGN KEY (product_document_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_item ADD CONSTRAINT FK_DC5CFACD629A4E51 FOREIGN KEY (machine_document_id) REFERENCES machine (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
    }
}
