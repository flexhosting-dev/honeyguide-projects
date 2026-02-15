<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hidden_project_ids column to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD hidden_project_ids JSON DEFAULT NULL');
        $this->addSql('UPDATE `user` SET hidden_project_ids = \'[]\' WHERE hidden_project_ids IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP hidden_project_ids');
    }
}
