<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319190857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add client agent support: isClientAgent flag, managed clients relation, onBehalfOfClient on Order and Inquiry';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE client_agent_managed_clients (agent_client_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', managed_client_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', INDEX IDX_B5A1BB5DEEBFEF65 (agent_client_id), INDEX IDX_B5A1BB5D1583A890 (managed_client_id), PRIMARY KEY(agent_client_id, managed_client_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_agent_managed_clients ADD CONSTRAINT FK_B5A1BB5DEEBFEF65 FOREIGN KEY (agent_client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_agent_managed_clients ADD CONSTRAINT FK_B5A1BB5D1583A890 FOREIGN KEY (managed_client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client ADD is_client_agent TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry ADD on_behalf_of_client_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry ADD CONSTRAINT FK_5A3903F03984F347 FOREIGN KEY (on_behalf_of_client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5A3903F03984F347 ON inquiry (on_behalf_of_client_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD on_behalf_of_client_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD CONSTRAINT FK_F52993983984F347 FOREIGN KEY (on_behalf_of_client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F52993983984F347 ON `order` (on_behalf_of_client_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client_agent_managed_clients DROP FOREIGN KEY FK_B5A1BB5DEEBFEF65
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_agent_managed_clients DROP FOREIGN KEY FK_B5A1BB5D1583A890
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE client_agent_managed_clients
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client DROP is_client_agent
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry DROP FOREIGN KEY FK_5A3903F03984F347
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_5A3903F03984F347 ON inquiry
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inquiry DROP on_behalf_of_client_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP FOREIGN KEY FK_F52993983984F347
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_F52993983984F347 ON `order`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP on_behalf_of_client_id
        SQL);
    }
}
