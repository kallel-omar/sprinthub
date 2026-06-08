<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608000648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, workspace_id INT DEFAULT NULL, project_id INT DEFAULT NULL, task_id INT DEFAULT NULL, INDEX IDX_FD06F647A76ED395 (user_id), INDEX IDX_FD06F64782D40A1F (workspace_id), INDEX IDX_FD06F647166D1F9C (project_id), INDEX IDX_FD06F6478DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F64782D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id)');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F6478DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647A76ED395');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F64782D40A1F');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647166D1F9C');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F6478DB60186');
        $this->addSql('DROP TABLE activity_log');
    }
}
