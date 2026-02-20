<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add position and isDefault fields to milestone table
 */
final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position and is_default columns to milestone table with proper indexing';
    }

    public function up(Schema $schema): void
    {
        // Add columns
        $this->addSql('ALTER TABLE milestone ADD position INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE milestone ADD is_default TINYINT(1) DEFAULT 0 NOT NULL');

        // Create index
        $this->addSql('CREATE INDEX idx_milestone_position ON milestone (project_id, position)');

        // Assign positions to existing milestones (ordered by dueDate, createdAt)
        $this->addSql("
            SET @pos := -1;
            SET @current_project := NULL;
            UPDATE milestone m
            INNER JOIN (
                SELECT id,
                    @pos := IF(@current_project = project_id, @pos + 1, 0) AS new_position,
                    @current_project := project_id
                FROM milestone
                ORDER BY project_id,
                    CASE WHEN due_date IS NULL THEN 1 ELSE 0 END,
                    due_date ASC, created_at ASC
            ) AS sorted ON m.id = sorted.id
            SET m.position = sorted.new_position + 1
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_milestone_position ON milestone');
        $this->addSql('ALTER TABLE milestone DROP position');
        $this->addSql('ALTER TABLE milestone DROP is_default');
    }
}
