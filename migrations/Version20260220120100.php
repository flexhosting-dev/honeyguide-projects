<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add position field to project table
 */
final class Version20260220120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position column to project table for admin-controlled ordering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD position INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_project_position ON project (position)');

        // Assign sequential positions by created_at
        $this->addSql("
            SET @pos := -1;
            UPDATE project p
            INNER JOIN (
                SELECT id, (@pos := @pos + 1) AS new_position
                FROM project ORDER BY created_at ASC
            ) AS sorted ON p.id = sorted.id
            SET p.position = sorted.new_position
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_project_position ON project');
        $this->addSql('ALTER TABLE project DROP position');
    }
}
