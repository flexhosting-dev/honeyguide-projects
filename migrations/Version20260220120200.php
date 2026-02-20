<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create default "General" milestone for all projects
 */
final class Version20260220120200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create default "General" milestone for all projects and shift existing milestones';
    }

    public function up(Schema $schema): void
    {
        // First, mark existing "General" milestones as default and set to position 0
        $this->addSql("
            UPDATE milestone
            SET is_default = 1, position = 0
            WHERE name = 'General'
            AND project_id IN (
                SELECT p.id FROM project p
                WHERE NOT EXISTS (
                    SELECT 1 FROM milestone m2
                    WHERE m2.project_id = p.id AND m2.is_default = 1
                )
            )
            AND id IN (
                SELECT MIN(id) FROM (SELECT * FROM milestone) AS m3
                WHERE m3.name = 'General'
                GROUP BY m3.project_id
            )
        ");

        // Insert "General" milestone for projects that don't have one
        $this->addSql("
            INSERT INTO milestone (id, project_id, name, status, position, is_default, created_at, updated_at)
            SELECT UUID(), p.id, 'General', 'open', 0, 1, NOW(), NOW()
            FROM project p
            WHERE NOT EXISTS (
                SELECT 1 FROM milestone m
                WHERE m.project_id = p.id AND m.is_default = 1
            )
        ");

        // Shift existing non-default milestones to position 1+
        $this->addSql("UPDATE milestone SET position = position + 1 WHERE is_default = 0");
    }

    public function down(Schema $schema): void
    {
        // Remove default milestones
        $this->addSql("DELETE FROM milestone WHERE is_default = 1");

        // Shift remaining milestones back
        $this->addSql("UPDATE milestone SET position = position - 1 WHERE position > 0");
    }
}
