<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607221356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_comment DROP FOREIGN KEY `FK_8B957886F675F31B`');
        $this->addSql('DROP INDEX IDX_8B957886F675F31B ON task_comment');
        $this->addSql('ALTER TABLE task_comment CHANGE author_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE task_comment ADD CONSTRAINT FK_8B957886A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_8B957886A76ED395 ON task_comment (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_comment DROP FOREIGN KEY FK_8B957886A76ED395');
        $this->addSql('DROP INDEX IDX_8B957886A76ED395 ON task_comment');
        $this->addSql('ALTER TABLE task_comment CHANGE user_id author_id INT NOT NULL');
        $this->addSql('ALTER TABLE task_comment ADD CONSTRAINT `FK_8B957886F675F31B` FOREIGN KEY (author_id) REFERENCES task (id)');
        $this->addSql('CREATE INDEX IDX_8B957886F675F31B ON task_comment (author_id)');
    }
}
