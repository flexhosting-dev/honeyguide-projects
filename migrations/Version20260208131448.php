<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

final class Version20260208131448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add changelog table with initial release entry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE changelog (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', version VARCHAR(20) NOT NULL, title VARCHAR(100) NOT NULL, changes JSON NOT NULL COMMENT \'(DC2Type:json)\', release_date DATE NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Seed initial changelog entry
        $id = Uuid::uuid4()->toString();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $changes = json_encode([
            'Project management with Kanban boards',
            'Task management with subtasks and checklists',
            'Milestone tracking and Gantt charts',
            'Team collaboration with comments and mentions',
            'Role-based permissions and user management',
            'Real-time notifications',
        ]);
        $this->addSql("INSERT INTO changelog (id, version, title, changes, release_date, created_at, updated_at) VALUES ('{$id}', '1.0.0', 'Initial Release', '{$changes}', '2026-02-08', '{$now}', '{$now}')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE changelog');
    }
}
