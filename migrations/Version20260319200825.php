<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319200825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine ADD on_behalf_of_client_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine ADD CONSTRAINT FK_FE18B1213984F347 FOREIGN KEY (on_behalf_of_client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FE18B1213984F347 ON inquiry_machine (on_behalf_of_client_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_item ADD on_behalf_of_client_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F093984F347 FOREIGN KEY (on_behalf_of_client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_52EA1F093984F347 ON order_item (on_behalf_of_client_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine DROP FOREIGN KEY FK_FE18B1213984F347
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_FE18B1213984F347 ON inquiry_machine
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry_machine DROP on_behalf_of_client_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F093984F347
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_52EA1F093984F347 ON order_item
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_item DROP on_behalf_of_client_id
        SQL);
    }
}
