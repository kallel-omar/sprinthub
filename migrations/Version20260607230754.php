<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607230754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE workspace_member (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(50) NOT NULL, workspace_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_40242BD082D40A1F (workspace_id), INDEX IDX_40242BD0A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE workspace_member ADD CONSTRAINT FK_40242BD082D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id)');
        $this->addSql('ALTER TABLE workspace_member ADD CONSTRAINT FK_40242BD0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workspace_member DROP FOREIGN KEY FK_40242BD082D40A1F');
        $this->addSql('ALTER TABLE workspace_member DROP FOREIGN KEY FK_40242BD0A76ED395');
        $this->addSql('DROP TABLE workspace_member');
    }
}
