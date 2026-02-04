<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create task_status_type table and migrate tasks to use it';
    }

    public function up(Schema $schema): void
    {
        // Add status_type_id column to task table if it doesn't exist
        $this->addSql('ALTER TABLE task ADD status_type_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2570A22CE8 FOREIGN KEY (status_type_id) REFERENCES task_status_type (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_527EDB2570A22CE8 ON task (status_type_id)');

        // Migrate existing task statuses to the new system
        $this->addSql('UPDATE task t JOIN task_status_type st ON st.slug = t.status SET t.status_type_id = st.id');
    }

    public function down(Schema $schema): void
    {
        // Remove the foreign key and column
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2570A22CE8');
        $this->addSql('DROP INDEX IDX_527EDB2570A22CE8 ON task');
        $this->addSql('ALTER TABLE task DROP status_type_id');
    }
}
