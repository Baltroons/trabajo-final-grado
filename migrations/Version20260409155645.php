<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409155645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sala_user (sala_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY (sala_id, user_id))');
        $this->addSql('CREATE INDEX IDX_AA4CE0BAC51CDF3F ON sala_user (sala_id)');
        $this->addSql('CREATE INDEX IDX_AA4CE0BAA76ED395 ON sala_user (user_id)');
        $this->addSql('ALTER TABLE sala_user ADD CONSTRAINT FK_AA4CE0BAC51CDF3F FOREIGN KEY (sala_id) REFERENCES sala (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sala_user ADD CONSTRAINT FK_AA4CE0BAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sala_user DROP CONSTRAINT FK_AA4CE0BAC51CDF3F');
        $this->addSql('ALTER TABLE sala_user DROP CONSTRAINT FK_AA4CE0BAA76ED395');
        $this->addSql('DROP TABLE sala_user');
    }
}
