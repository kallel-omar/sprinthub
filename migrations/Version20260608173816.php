<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608173816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_checklist_item (id INT AUTO_INCREMENT NOT NULL, content VARCHAR(255) NOT NULL, is_done TINYINT NOT NULL, created_at DATETIME NOT NULL, task_id INT NOT NULL, INDEX IDX_85C256568DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE task_checklist_item ADD CONSTRAINT FK_85C256568DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_checklist_item DROP FOREIGN KEY FK_85C256568DB60186');
        $this->addSql('DROP TABLE task_checklist_item');
    }
}
