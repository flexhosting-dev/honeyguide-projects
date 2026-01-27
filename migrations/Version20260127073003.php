<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127073003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_checklist RENAME INDEX idx_task_checklist_task TO IDX_2343A07E8DB60186');
        $this->addSql('ALTER TABLE user ADD google_id VARCHAR(255) DEFAULT NULL, CHANGE password password VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP google_id, CHANGE password password VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE task_checklist RENAME INDEX idx_2343a07e8db60186 TO IDX_TASK_CHECKLIST_TASK');
    }
}
