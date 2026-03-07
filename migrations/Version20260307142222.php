<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260307142222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add push_subscription table for Web Push notifications';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE push_subscription (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', endpoint LONGTEXT NOT NULL, endpoint_hash VARCHAR(64) NOT NULL, p256dh_key LONGTEXT NOT NULL, auth_token LONGTEXT NOT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_used_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_push_subscription_user (user_id), INDEX idx_push_subscription_last_used (last_used_at), UNIQUE INDEX uniq_user_endpoint (user_id, endpoint_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT FK_562830F3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE push_subscription DROP FOREIGN KEY FK_562830F3A76ED395');
        $this->addSql('DROP TABLE push_subscription');
    }
}
