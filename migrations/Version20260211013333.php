<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211013333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add requires_order_approval and requires_inquiry_approval flags to client table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client ADD requires_order_approval TINYINT(1) DEFAULT 0 NOT NULL, ADD requires_inquiry_approval TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client DROP requires_order_approval, DROP requires_inquiry_approval
        SQL);
    }
}
