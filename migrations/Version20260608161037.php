<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608161037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_label (task_id INT NOT NULL, label_id INT NOT NULL, INDEX IDX_C9034BC88DB60186 (task_id), INDEX IDX_C9034BC833B92F39 (label_id), PRIMARY KEY (task_id, label_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE task_label ADD CONSTRAINT FK_C9034BC88DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_label ADD CONSTRAINT FK_C9034BC833B92F39 FOREIGN KEY (label_id) REFERENCES label (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE label_task DROP FOREIGN KEY `FK_9E464EE933B92F39`');
        $this->addSql('ALTER TABLE label_task DROP FOREIGN KEY `FK_9E464EE98DB60186`');
        $this->addSql('DROP TABLE label_task');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE label_task (label_id INT NOT NULL, task_id INT NOT NULL, INDEX IDX_9E464EE98DB60186 (task_id), INDEX IDX_9E464EE933B92F39 (label_id), PRIMARY KEY (label_id, task_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE label_task ADD CONSTRAINT `FK_9E464EE933B92F39` FOREIGN KEY (label_id) REFERENCES label (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE label_task ADD CONSTRAINT `FK_9E464EE98DB60186` FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_label DROP FOREIGN KEY FK_C9034BC88DB60186');
        $this->addSql('ALTER TABLE task_label DROP FOREIGN KEY FK_C9034BC833B92F39');
        $this->addSql('DROP TABLE task_label');
    }
}
