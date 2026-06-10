<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610183928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_join_request (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_C395009C166D1F9C (project_id), INDEX IDX_C395009CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_join_request ADD CONSTRAINT FK_C395009C166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE project_join_request ADD CONSTRAINT FK_C395009CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_join_request DROP FOREIGN KEY FK_C395009C166D1F9C');
        $this->addSql('ALTER TABLE project_join_request DROP FOREIGN KEY FK_C395009CA76ED395');
        $this->addSql('DROP TABLE project_join_request');
    }
}
