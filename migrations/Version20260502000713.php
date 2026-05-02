<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502000713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mensaje DROP CONSTRAINT fk_9b631d01386d8d01');
        $this->addSql('DROP INDEX idx_9b631d01386d8d01');
        $this->addSql('ALTER TABLE mensaje ADD archivo_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE mensaje ADD archivo_nombre VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE mensaje DROP receptor_id');
        $this->addSql('ALTER TABLE mensaje ALTER contenido DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mensaje ADD receptor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mensaje DROP archivo_url');
        $this->addSql('ALTER TABLE mensaje DROP archivo_nombre');
        $this->addSql('ALTER TABLE mensaje ALTER contenido SET NOT NULL');
        $this->addSql('ALTER TABLE mensaje ADD CONSTRAINT fk_9b631d01386d8d01 FOREIGN KEY (receptor_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_9b631d01386d8d01 ON mensaje (receptor_id)');
    }
}
