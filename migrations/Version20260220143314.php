<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220143314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_invitation (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', project_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', invited_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', invited_user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', role_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', reviewed_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', email VARCHAR(180) NOT NULL, status VARCHAR(20) NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_E9BB1A905F37A13B (token), INDEX IDX_E9BB1A90166D1F9C (project_id), INDEX IDX_E9BB1A90A7B4A7E3 (invited_by_id), INDEX IDX_E9BB1A90C58DAD6E (invited_user_id), INDEX IDX_E9BB1A90D60322AC (role_id), INDEX IDX_E9BB1A90FC6B21F1 (reviewed_by_id), INDEX idx_invitation_email (email), INDEX idx_invitation_status (status), INDEX idx_invitation_token (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE project_invitation ADD CONSTRAINT FK_E9BB1A90166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_invitation ADD CONSTRAINT FK_E9BB1A90A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_invitation ADD CONSTRAINT FK_E9BB1A90C58DAD6E FOREIGN KEY (invited_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_invitation ADD CONSTRAINT FK_E9BB1A90D60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE project_invitation ADD CONSTRAINT FK_E9BB1A90FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_milestone_position ON milestone');
        $this->addSql('ALTER TABLE milestone CHANGE position position INT NOT NULL');
        $this->addSql('DROP INDEX idx_project_position ON project');
        $this->addSql('ALTER TABLE project CHANGE position position INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_invitation DROP FOREIGN KEY FK_E9BB1A90166D1F9C');
        $this->addSql('ALTER TABLE project_invitation DROP FOREIGN KEY FK_E9BB1A90A7B4A7E3');
        $this->addSql('ALTER TABLE project_invitation DROP FOREIGN KEY FK_E9BB1A90C58DAD6E');
        $this->addSql('ALTER TABLE project_invitation DROP FOREIGN KEY FK_E9BB1A90D60322AC');
        $this->addSql('ALTER TABLE project_invitation DROP FOREIGN KEY FK_E9BB1A90FC6B21F1');
        $this->addSql('DROP TABLE project_invitation');
        $this->addSql('ALTER TABLE milestone CHANGE position position INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_milestone_position ON milestone (project_id, position)');
        $this->addSql('ALTER TABLE project CHANGE position position INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_project_position ON project (position)');
    }
}
