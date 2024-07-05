<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240702185437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes CHANGE idutilisateur_id idutilisateur_id INT DEFAULT NULL, CHANGE statuts statuts VARCHAR(255) DEFAULT NULL, CHANGE titre titre VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes CHANGE idutilisateur_id idutilisateur_id INT NOT NULL, CHANGE statuts statuts VARCHAR(255) NOT NULL, CHANGE titre titre VARCHAR(255) NOT NULL');
    }
}
