<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unused task_table_preferences column from user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP task_table_preferences');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD task_table_preferences JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }
}
