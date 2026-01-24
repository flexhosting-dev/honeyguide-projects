<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add tag and task_tag tables for tagging system.
 */
final class Version20260124150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tag and task_tag tables for tagging system';
    }

    public function up(Schema $schema): void
    {
        // Create tag table
        $this->addSql('CREATE TABLE tag (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(50) NOT NULL, color VARCHAR(7) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_389B7835E237E06 (name), INDEX IDX_389B783B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_TAG_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id)');

        // Create task_tag junction table
        $this->addSql('CREATE TABLE task_tag (task_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', tag_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_6C0B4F048DB60186 (task_id), INDEX IDX_6C0B4F04BAD26311 (tag_id), PRIMARY KEY(task_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE task_tag ADD CONSTRAINT FK_TASK_TAG_TASK FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_tag ADD CONSTRAINT FK_TASK_TAG_TAG FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_tag DROP FOREIGN KEY FK_TASK_TAG_TASK');
        $this->addSql('ALTER TABLE task_tag DROP FOREIGN KEY FK_TASK_TAG_TAG');
        $this->addSql('DROP TABLE task_tag');
        $this->addSql('ALTER TABLE tag DROP FOREIGN KEY FK_TAG_CREATED_BY');
        $this->addSql('DROP TABLE tag');
    }
}
