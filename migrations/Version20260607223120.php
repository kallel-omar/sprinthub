<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607223120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_attachment (id INT AUTO_INCREMENT NOT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, task_id INT NOT NULL, uploaded_by_id INT NOT NULL, INDEX IDX_654C92148DB60186 (task_id), INDEX IDX_654C9214A2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE task_attachment ADD CONSTRAINT FK_654C92148DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE task_attachment ADD CONSTRAINT FK_654C9214A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_attachment DROP FOREIGN KEY FK_654C92148DB60186');
        $this->addSql('ALTER TABLE task_attachment DROP FOREIGN KEY FK_654C9214A2B28FE8');
        $this->addSql('DROP TABLE task_attachment');
    }
}
