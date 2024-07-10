<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240709143237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE historique_demande (id INT AUTO_INCREMENT NOT NULL, demande_id INT DEFAULT NULL, commentaire VARCHAR(255) DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, date DATETIME DEFAULT NULL, INDEX IDX_448088DA80E95E18 (demande_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE historique_demande ADD CONSTRAINT FK_448088DA80E95E18 FOREIGN KEY (demande_id) REFERENCES demandes (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE historique_demande DROP FOREIGN KEY FK_448088DA80E95E18');
        $this->addSql('DROP TABLE historique_demande');
    }
}
