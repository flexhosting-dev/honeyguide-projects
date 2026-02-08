<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

final class Version20260208142100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add changelog entry for external user registration requests feature';
    }

    public function up(Schema $schema): void
    {
        $id = Uuid::uuid4()->toString();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $changes = json_encode([
            'Added pending registration request workflow for external domain users',
            'Supports both Google OAuth and manual email/password registration',
            'Portal admins receive email and in-app notifications for new requests',
            'Added Pending Requests tab to User Management page',
        ]);
        $this->addSql("INSERT INTO changelog (id, version, title, changes, release_date, created_at, updated_at) VALUES ('{$id}', '1.1.0', 'External User Registration Requests', '{$changes}', '2026-02-08', '{$now}', '{$now}')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM changelog WHERE version = '1.1.0' AND title = 'External User Registration Requests'");
    }
}
