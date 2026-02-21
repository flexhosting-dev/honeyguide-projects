<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220185730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase project_invitation status column length to support pending_admin_approval';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_invitation MODIFY status VARCHAR(30) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_invitation MODIFY status VARCHAR(20) NOT NULL');
    }
}
