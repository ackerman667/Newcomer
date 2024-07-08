<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240705153709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes ADD adresse_academique TINYINT(1) DEFAULT NULL, ADD statut_demande VARCHAR(255) DEFAULT NULL, ADD nom_remplacant VARCHAR(255) DEFAULT NULL, ADD prenom_remplacant VARCHAR(255) DEFAULT NULL, ADD telephone_remplacant VARCHAR(255) DEFAULT NULL, ADD depart TINYINT(1) DEFAULT NULL, ADD affectation_remplacant VARCHAR(255) DEFAULT NULL, ADD charte TINYINT(1) DEFAULT NULL, ADD imprimante TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes DROP adresse_academique, DROP statut_demande, DROP nom_remplacant, DROP prenom_remplacant, DROP telephone_remplacant, DROP depart, DROP affectation_remplacant, DROP charte, DROP imprimante');
    }
}
