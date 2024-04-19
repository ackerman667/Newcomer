<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240326132428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demandes (id INT AUTO_INCREMENT NOT NULL, idutilisateur_id INT NOT NULL, date DATE NOT NULL, statuts VARCHAR(255) NOT NULL, date_autorisation DATE NOT NULL, INDEX IDX_BD940CBBEAF07004 (idutilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demandes ADD CONSTRAINT FK_BD940CBBEAF07004 FOREIGN KEY (idutilisateur_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes DROP FOREIGN KEY FK_BD940CBBEAF07004');
        $this->addSql('DROP TABLE demandes');
    }
}
