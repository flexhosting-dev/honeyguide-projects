<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create task_status_type table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_status_type (
            id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            parent_type VARCHAR(20) NOT NULL,
            color VARCHAR(7) NOT NULL,
            icon VARCHAR(50) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            sort_order INT NOT NULL,
            is_default TINYINT(1) NOT NULL,
            is_system TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_TASK_STATUS_SLUG (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Insert default status types
        $this->addSql("INSERT INTO task_status_type (id, name, slug, parent_type, color, icon, sort_order, is_default, is_system, created_at, updated_at) VALUES
            (UUID(), 'Open', 'open', 'open', '#3B82F6', 'circle', 0, 1, 1, NOW(), NOW()),
            (UUID(), 'In Progress', 'in_progress', 'open', '#F59E0B', 'clock', 1, 0, 1, NOW(), NOW()),
            (UUID(), 'Completed', 'completed', 'closed', '#10B981', 'check-circle', 2, 0, 1, NOW(), NOW()),
            (UUID(), 'Cancelled', 'cancelled', 'closed', '#EF4444', 'x-circle', 3, 0, 1, NOW(), NOW())
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task_status_type');
    }
}
