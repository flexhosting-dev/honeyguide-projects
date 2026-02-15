<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128043434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add favourite_project_ids column to user table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD favourite_project_ids JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('UPDATE user SET favourite_project_ids = \'[]\' WHERE favourite_project_ids IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP favourite_project_ids');
    }
}
