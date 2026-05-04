<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502122349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mensaje ADD receptor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mensaje ADD CONSTRAINT FK_9B631D01386D8D01 FOREIGN KEY (receptor_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_9B631D01386D8D01 ON mensaje (receptor_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mensaje DROP CONSTRAINT FK_9B631D01386D8D01');
        $this->addSql('DROP INDEX IDX_9B631D01386D8D01');
        $this->addSql('ALTER TABLE mensaje DROP receptor_id');
    }
}
