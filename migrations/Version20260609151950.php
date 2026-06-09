<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260609151950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_members (project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_D3BEDE9A166D1F9C (project_id), INDEX IDX_D3BEDE9AA76ED395 (user_id), PRIMARY KEY (project_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_D3BEDE9A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_D3BEDE9AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_members DROP FOREIGN KEY FK_D3BEDE9A166D1F9C');
        $this->addSql('ALTER TABLE project_members DROP FOREIGN KEY FK_D3BEDE9AA76ED395');
        $this->addSql('DROP TABLE project_members');
    }
}
