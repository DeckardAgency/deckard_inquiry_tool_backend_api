<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120150248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine DROP FOREIGN KEY FK_FE18B121F6B75B26
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine ADD CONSTRAINT FK_FE18B121F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine DROP FOREIGN KEY FK_FE18B121F6B75B26
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine ADD CONSTRAINT FK_FE18B121F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
    }
}
