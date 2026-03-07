<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260307154344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix user deletion foreign key constraints - allow deletion with orphaned comments and task assignments';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE comment CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task_assignee DROP FOREIGN KEY FK_3C5D16406E6F1246');
        $this->addSql('ALTER TABLE task_assignee ADD CONSTRAINT FK_3C5D16406E6F1246 FOREIGN KEY (assigned_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_assignee DROP FOREIGN KEY FK_3C5D16406E6F1246');
        $this->addSql('ALTER TABLE task_assignee ADD CONSTRAINT FK_3C5D16406E6F1246 FOREIGN KEY (assigned_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE comment CHANGE author_id author_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
    }
}
