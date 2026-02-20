<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add changelog entry for default milestones and ordering feature
 */
final class Version20260220120300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add changelog entry for version 1.0.1 - Default Milestones and Ordering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO changelog (id, version, title, changes, release_date, created_at, updated_at)
            VALUES (
                UUID(),
                '1.0.1',
                'Default Milestones and Ordering',
                '[\"Added default General milestone to all projects\", \"Tasks auto-assigned to General if no milestone specified\", \"Added position-based ordering for projects, milestones, and tasks\", \"Added milestone reordering within projects (PROJECT_EDIT permission)\", \"Added admin project reordering (admin only)\", \"All Tasks page now respects project and milestone ordering\", \"Default milestone cannot be deleted or renamed\"]',
                CURDATE(),
                NOW(),
                NOW()
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM changelog WHERE version = '1.0.1' AND title = 'Default Milestones and Ordering'");
    }
}
