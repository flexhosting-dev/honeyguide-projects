<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208142048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending_registration_request table for domain-restricted registration workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pending_registration_request (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', reviewed_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, google_id VARCHAR(255) DEFAULT NULL, password_hash VARCHAR(255) DEFAULT NULL, domain VARCHAR(100) NOT NULL, registration_type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C17B1042FC6B21F1 (reviewed_by_id), INDEX idx_pending_reg_status (status), INDEX idx_pending_reg_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pending_registration_request ADD CONSTRAINT FK_C17B1042FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pending_registration_request DROP FOREIGN KEY FK_C17B1042FC6B21F1');
        $this->addSql('DROP TABLE pending_registration_request');
    }
}
