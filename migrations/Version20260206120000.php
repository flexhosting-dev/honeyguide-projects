<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add task_table_preferences column to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD task_table_preferences JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('UPDATE `user` SET task_table_preferences = \'{}\' WHERE task_table_preferences IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP task_table_preferences');
    }
}
