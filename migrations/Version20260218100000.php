<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add recurrence fields to task table for recurring task functionality
 */
final class Version20260218100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recurrence fields to task table';
    }

    public function up(Schema $schema): void
    {
        // Add recurrence columns to task table
        // Note: Using CHAR(36) to match existing UUID columns in task table
        $this->addSql('ALTER TABLE task ADD recurrence_rule JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE task ADD recurrence_series_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE task ADD recurrence_parent_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE task ADD recurrence_ends_at DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE task ADD recurrence_count_remaining INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD recurrence_overrides JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');

        // Add indexes for better query performance
        $this->addSql('CREATE INDEX idx_task_recurrence_series ON task (recurrence_series_id)');
        $this->addSql('CREATE INDEX IDX_527EDB256ADCDB06 ON task (recurrence_parent_id)');

        // Add foreign key for recurrence_parent_id
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB256ADCDB06 FOREIGN KEY (recurrence_parent_id) REFERENCES task (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign key
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB256ADCDB06');

        // Remove indexes
        $this->addSql('DROP INDEX idx_task_recurrence_series ON task');
        $this->addSql('DROP INDEX IDX_527EDB256ADCDB06 ON task');

        // Remove columns
        $this->addSql('ALTER TABLE task DROP recurrence_rule');
        $this->addSql('ALTER TABLE task DROP recurrence_series_id');
        $this->addSql('ALTER TABLE task DROP recurrence_parent_id');
        $this->addSql('ALTER TABLE task DROP recurrence_ends_at');
        $this->addSql('ALTER TABLE task DROP recurrence_count_remaining');
        $this->addSql('ALTER TABLE task DROP recurrence_overrides');
    }
}
