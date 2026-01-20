<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to update priority values:
 * - urgent → high
 * - high → medium
 * - medium → low
 * - low → none
 */
final class Version20260120140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restructure priority levels: remove urgent, shift all down, add none';
    }

    public function up(Schema $schema): void
    {
        // Use temporary values to avoid conflicts during update
        $this->addSql("UPDATE task SET priority = 'tmp_high' WHERE priority = 'urgent'");
        $this->addSql("UPDATE task SET priority = 'tmp_medium' WHERE priority = 'high'");
        $this->addSql("UPDATE task SET priority = 'tmp_low' WHERE priority = 'medium'");
        $this->addSql("UPDATE task SET priority = 'none' WHERE priority = 'low'");

        // Now convert temporary values to final values
        $this->addSql("UPDATE task SET priority = 'high' WHERE priority = 'tmp_high'");
        $this->addSql("UPDATE task SET priority = 'medium' WHERE priority = 'tmp_medium'");
        $this->addSql("UPDATE task SET priority = 'low' WHERE priority = 'tmp_low'");
    }

    public function down(Schema $schema): void
    {
        // Reverse: shift all up, convert none back to low, add urgent
        $this->addSql("UPDATE task SET priority = 'tmp_urgent' WHERE priority = 'high'");
        $this->addSql("UPDATE task SET priority = 'tmp_high' WHERE priority = 'medium'");
        $this->addSql("UPDATE task SET priority = 'tmp_medium' WHERE priority = 'low'");
        $this->addSql("UPDATE task SET priority = 'low' WHERE priority = 'none'");

        $this->addSql("UPDATE task SET priority = 'urgent' WHERE priority = 'tmp_urgent'");
        $this->addSql("UPDATE task SET priority = 'high' WHERE priority = 'tmp_high'");
        $this->addSql("UPDATE task SET priority = 'medium' WHERE priority = 'tmp_medium'");
    }
}
