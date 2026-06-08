<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607220244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, task_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_8B9578868DB60186 (task_id), INDEX IDX_8B957886F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE task_comment ADD CONSTRAINT FK_8B9578868DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE task_comment ADD CONSTRAINT FK_8B957886F675F31B FOREIGN KEY (author_id) REFERENCES task (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_comment DROP FOREIGN KEY FK_8B9578868DB60186');
        $this->addSql('ALTER TABLE task_comment DROP FOREIGN KEY FK_8B957886F675F31B');
        $this->addSql('DROP TABLE task_comment');
    }
}
