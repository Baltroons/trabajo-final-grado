<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501202926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD foto_perfil VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD biografia TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD ciudad VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP foto_perfil');
        $this->addSql('ALTER TABLE "user" DROP biografia');
        $this->addSql('ALTER TABLE "user" DROP ciudad');
    }
}
